<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListAvailableNodesTool implements Tool
{
    public function __construct(public readonly WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'list_available_nodes';
    }

    public function description(): Stringable|string
    {
        return "List every node type you can add to the draft. Each entry gives the 'type' to use in add_node, its category, name, and description.";
    }

    public function handle(Request $request): Stringable|string
    {
        $registry = app(NodeRegistry::class);

        $nodes = collect($registry->connectors())
            ->map(fn (string $class, string $type) => [
                'type' => $type,
                'name' => app($class)->name(),
                'description' => app($class)->description(),
                'category' => app($class)->category(),
            ])
            ->values();

        return json_encode($nodes->all(), JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
