<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class InspectNodeSchemaTool implements Tool
{
    public function __construct(public readonly WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'inspect_node_schema';
    }

    public function description(): Stringable|string
    {
        return "Get the config schema for a node type, so you know exactly which fields to set in a node's 'config'. Takes the 'type' from list_available_nodes.";
    }

    public function handle(Request $request): Stringable|string
    {
        $type = (string) ($request->all()['type'] ?? '');
        $registry = app(NodeRegistry::class);

        if (! $registry->has($type)) {
            return "No node found with type [{$type}]. Use list_available_nodes to see valid types.";
        }

        $node = $registry->resolve($type);

        return json_encode([
            'type' => $type,
            'name' => $node->name(),
            'description' => $node->description(),
            'category' => $node->category(),
            'config_schema' => $node->configSchema(),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('The node type to inspect, e.g. "call_api" or "router".')->required(),
        ];
    }
}
