<?php

namespace App\Services\Workflows;

use App\Contracts\NodeContract;
use App\Models\Nodes\CustomNode;
use App\Nodes\Custom\CustomHttpNode;
use InvalidArgumentException;

/**
 * Resolves a `workflow_nodes.type` string to an executable `NodeContract`
 * instance. Built-in types are bound at boot by `NodeRegistryServiceProvider`;
 * `custom:{id}` types resolve to a workspace-scoped `CustomNode` row wrapped
 * in `CustomHttpNode` — see docs/WORKFLOWS_PLAN.md's "Node contract &
 * registry" section, and `has()` for why the workspace must be named.
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
     * node for this type?", so this must never answer `true` where `resolve()`
     * would throw. Answering optimistically and throwing later turned a
     * missing feature into a hard error on every has()-then-resolve() caller:
     * saving a canvas (`Workflow::replaceGraph()`), publishing it
     * (`GraphValidator`), testing a node (`NodeTester`), binding one as an
     * agent tool, and every agent turn whose bindings included one. A `false`
     * puts the type on exactly the path the engine already has for a type it
     * doesn't know — schema checks skip it, `ToolRegistry` drops the binding,
     * and a run that reaches one fails that single node with `resolve()`'s
     * message instead of taking the whole request down.
     *
     * `$workspaceId` is **required** to get a `true` for a `custom:{id}` type.
     * A custom node belongs to one workspace, and a caller that can't say
     * which workspace it is asking on behalf of has no business resolving one
     * — so the tenant-unaware default is to refuse. See `customNode()`.
     */
    public function has(string $type, ?int $workspaceId = null): bool
    {
        if (str_starts_with($type, self::CUSTOM_PREFIX)) {
            return $this->customNode($type, $workspaceId) !== null;
        }

        return isset($this->builtins[$type]);
    }

    public function resolve(string $type, ?int $workspaceId = null): NodeContract
    {
        if (str_starts_with($type, self::CUSTOM_PREFIX)) {
            return $this->resolveCustom($type, $workspaceId);
        }

        if (! isset($this->builtins[$type])) {
            throw new InvalidArgumentException("No node is registered for type [{$type}].");
        }

        return app($this->builtins[$type]);
    }

    /**
     * The `CustomNode` behind a `custom:{id}` type, or null when there isn't
     * an executable one — no such row, wrong workspace, deactivated, or a
     * definition whose author never supplied an `implementation`.
     *
     * Scoping by `workspace_id` in the query (rather than loading by id and
     * comparing afterwards) is what stops a graph in workspace B from
     * referencing workspace A's node by guessing its id.
     */
    private function customNode(string $type, ?int $workspaceId): ?CustomNode
    {
        if ($workspaceId === null) {
            return null;
        }

        $id = substr($type, strlen(self::CUSTOM_PREFIX));

        if (! ctype_digit($id)) {
            return null;
        }

        $node = CustomNode::query()
            ->where('workspace_id', $workspaceId)
            ->find((int) $id);

        return $node?->isExecutable() === true ? $node : null;
    }

    /**
     * Reached only from the engine's own execute path when `has()` said false
     * — the message is persisted onto the failing `NodeRun` and read by
     * whoever is watching the run, so it stays user-facing and says which of
     * the several reasons applies.
     */
    private function resolveCustom(string $type, ?int $workspaceId): NodeContract
    {
        $node = $this->customNode($type, $workspaceId);

        if ($node === null) {
            throw new InvalidArgumentException(
                "Custom node [{$type}] is not available in this workspace, or has no implementation to run."
            );
        }

        return match ($node->implementation['kind']) {
            CustomHttpNode::KIND => app(CustomHttpNode::class, ['node' => $node]),
            default => throw new InvalidArgumentException(
                "Custom node [{$type}] has an unsupported implementation kind [{$node->implementation['kind']}]."
            ),
        };
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
