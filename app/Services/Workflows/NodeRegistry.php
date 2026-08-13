<?php

namespace App\Services\Workflows;

use App\Contracts\NodeContract;
use App\Models\Nodes\CustomNode;
use InvalidArgumentException;

/**
 * Resolves a `workflow_nodes.type` string to an executable `NodeContract`
 * instance. Built-in types are bound at boot by `NodeRegistryServiceProvider`;
 * `custom:{id}` types resolve to a workspace-scoped `CustomNode` row instead —
 * see docs/WORKFLOWS_PLAN.md's "Node contract & registry" section.
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

    public function has(string $type): bool
    {
        if (str_starts_with($type, self::CUSTOM_PREFIX)) {
            return CustomNode::whereKey($this->customId($type))->exists();
        }

        return isset($this->builtins[$type]);
    }

    public function resolve(string $type): NodeContract
    {
        if (str_starts_with($type, self::CUSTOM_PREFIX)) {
            throw new InvalidArgumentException(
                "Custom node execution isn't implemented yet (type [{$type}]) — see docs/WORKFLOWS_AGENTS_BUILD_PLAN.md Stage 11.",
            );
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

    private function customId(string $type): int
    {
        return (int) substr($type, strlen(self::CUSTOM_PREFIX));
    }
}
