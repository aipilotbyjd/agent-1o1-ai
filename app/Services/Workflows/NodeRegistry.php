<?php

namespace App\Services\Workflows;

use App\Contracts\NodeContract;
use App\Enums\Workflows\FlowControlNodeType;
use App\Models\Nodes\NodeCategory;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Resolves a `workflow_nodes.type` string to an executable `NodeContract`
 * instance. Built-in types are bound at boot by `NodeRegistryServiceProvider`;
 * `custom:{id}` types will resolve to a workspace-scoped `CustomNode` row
 * once custom node execution exists — see docs/WORKFLOWS_PLAN.md's "Node
 * contract & registry" section, and `has()` for how they behave until then.
 */
class NodeRegistry
{
    private const string CUSTOM_PREFIX = 'custom:';

    /**
     * @var array<string, class-string<NodeContract>>
     */
    private array $builtins = [];

    /**
     * @param  class-string<NodeContract>  $class
     */
    public function register(string $type, string $class): void
    {
        $this->builtins[$type] = $class;
    }

    /**
     * Every caller is really asking "will `resolve()` hand me an executable
     * node for this type?", so a `custom:{id}` type answers **false** until
     * custom node execution lands (Stage 11) — a `CustomNode` row can be
     * authored today, but nothing can run one.
     *
     * Answering `true` for an authored-but-unrunnable custom node and then
     * throwing from `resolve()` turned an unimplemented feature into a hard
     * error on every has()-then-resolve() caller: saving a canvas
     * (`Workflow::replaceGraph()`), publishing it (`GraphValidator`),
     * testing a node (`NodeTester`), binding one as an agent tool, and every
     * agent turn whose bindings included one. Answering `false` puts custom
     * types on exactly the path the engine already has for a type it doesn't
     * know — schema checks skip it, `ToolRegistry` drops the binding, and a
     * run that reaches one fails that single node with `resolve()`'s message
     * instead of taking the whole request down.
     */
    public function has(string $type): bool
    {
        if (str_starts_with($type, self::CUSTOM_PREFIX)) {
            return false;
        }

        return isset($this->builtins[$type]);
    }

    public function resolve(string $type): NodeContract
    {
        // Reached only from the engine's own execute path (`has()` says
        // false, so nothing validates its way here) — the message is
        // persisted onto the failing `NodeRun` and read by whoever is
        // watching the run, so it stays user-facing. See
        // docs/WORKFLOWS_AGENTS_BUILD_PLAN.md Stage 11 for the work itself.
        if (str_starts_with($type, self::CUSTOM_PREFIX)) {
            throw new InvalidArgumentException("Custom nodes can't be executed yet (type [{$type}]).");
        }

        if (! isset($this->builtins[$type])) {
            throw new InvalidArgumentException("No node is registered for type [{$type}].");
        }

        return app($this->builtins[$type]);
    }

    /**
     * Every registered built-in node — what a `tool`-type step, and later an
     * Agent's tool list, can be pointed at.
     *
     * @return array<string, class-string<NodeContract>>
     */
    public function connectors(): array
    {
        return $this->builtins;
    }

    /**
     * Every built-in node's catalog metadata, including the engine-driven
     * flow-control types (Loop/JoinPaths/Wait/HumanApproval/SubWorkflow) —
     * see `has()`'s docblock for why those aren't in `$builtins`. `icon`/
     * `color` come from the node's `NodeCategory` row; `requires_connector`
     * is derived from whether the class uses `ResolvesConnectorCredential`.
     *
     * @return array<int, array{type: string, category: string, name: string, description: string, config_schema: array<string, mixed>, icon: ?string, color: ?string, requires_connector: bool}>
     */
    public function catalog(): array
    {
        $categories = NodeCategory::query()->get()->keyBy('slug');

        $builtins = collect($this->builtins)
            ->map(function (string $class, string $type) use ($categories): array {
                $node = app($class);
                $category = $categories->get($node->category());

                return [
                    'type' => $type,
                    'category' => $node->category(),
                    'name' => $node->name(),
                    'description' => $node->description(),
                    'config_schema' => $node->configSchema(),
                    'icon' => $category?->icon,
                    'color' => $category?->color,
                    'requires_connector' => $this->usesConnectorCredential($class),
                ];
            })
            ->values();

        return $builtins->concat($this->flowControlCatalog($categories))->all();
    }

    /**
     * @param  class-string<NodeContract>  $class
     */
    private function usesConnectorCredential(string $class): bool
    {
        return in_array(ResolvesConnectorCredential::class, class_uses_recursive($class), true);
    }

    /**
     * Static metadata for the flow-control types — they never go through
     * `execute()`, so there's no `NodeContract` instance to read from.
     *
     * @param  Collection<string, NodeCategory>  $categories  keyed by slug
     * @return array<int, array{type: string, category: string, name: string, description: string, config_schema: array<string, mixed>, icon: ?string, color: ?string, requires_connector: bool}>
     */
    private function flowControlCatalog(Collection $categories): array
    {
        $flowLogic = $categories->get('flow-logic');

        $nodes = [
            [
                'type' => FlowControlNodeType::Loop->value,
                'name' => 'Loop',
                'description' => 'Runs a child workflow once per item in a list, up to a concurrency limit.',
                'config_schema' => [
                    'type' => 'object',
                    'required' => ['items_path', 'workflow_id'],
                    'properties' => [
                        'items_path' => ['type' => 'string'],
                        'workflow_id' => ['type' => 'string'],
                        'max_concurrent' => ['type' => 'integer', 'default' => 1],
                        'on_item_error' => ['type' => 'string', 'enum' => ['fail_fast', 'continue', 'collect_errors'], 'default' => 'fail_fast'],
                    ],
                ],
            ],
            [
                'type' => FlowControlNodeType::JoinPaths->value,
                'name' => 'Join Paths',
                'description' => 'Waits for every incoming branch to reach it before continuing.',
                'config_schema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'type' => FlowControlNodeType::Wait->value,
                'name' => 'Wait',
                'description' => 'Pauses the run until an external callback resumes it, or until it times out.',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'timeout_seconds' => ['type' => ['integer', 'null']],
                        'continue_on_timeout' => ['type' => 'boolean', 'default' => false],
                    ],
                ],
            ],
            [
                'type' => FlowControlNodeType::HumanApproval->value,
                'name' => 'Human Approval',
                'description' => 'Pauses the run until a workspace member approves or rejects it.',
                'config_schema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'type' => FlowControlNodeType::SubWorkflow->value,
                'name' => 'Sub-Workflow',
                'description' => 'Runs another workflow as a child run and waits for it to complete.',
                'config_schema' => [
                    'type' => 'object',
                    'required' => ['workflow_id'],
                    'properties' => [
                        'workflow_id' => ['type' => 'string'],
                        'input' => ['type' => ['object', 'null']],
                    ],
                ],
            ],
        ];

        return array_map(fn (array $node): array => [
            ...$node,
            'category' => 'flow-logic',
            'icon' => $flowLogic?->icon,
            'color' => $flowLogic?->color,
            'requires_connector' => false,
        ], $nodes);
    }
}
