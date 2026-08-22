<?php

namespace App\Services\Workflows;

use App\Contracts\NodeContract;
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
     * Every built-in node's catalog metadata — what the node picker (and
     * `GET /workspaces/{workspace}/nodes`) renders alongside workspace
     * `CustomNode` rows.
     *
     * @return array<int, array{type: string, category: string, name: string, description: string, config_schema: array<string, mixed>}>
     */
    public function catalog(): array
    {
        return collect($this->builtins)
            ->map(function (string $class, string $type): array {
                $node = app($class);

                return [
                    'type' => $type,
                    'category' => $node->category(),
                    'name' => $node->name(),
                    'description' => $node->description(),
                    'config_schema' => $node->configSchema(),
                ];
            })
            ->values()
            ->all();
    }
}
