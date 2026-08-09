<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use JsonException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AddNodeTool implements Tool
{
    public function __construct(public readonly WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'add_node';
    }

    public function description(): Stringable|string
    {
        return "Add a new node to the workflow draft. The key must be unique within the draft. Use inspect_node_schema first to know what 'config' fields the chosen type expects.";
    }

    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->all();

        try {
            $config = isset($arguments['config_json'])
                ? json_decode((string) $arguments['config_json'], true, flags: JSON_THROW_ON_ERROR)
                : [];
        } catch (JsonException) {
            return 'config_json must be a valid JSON object string.';
        }

        try {
            $this->session->addNode(
                key: (string) $arguments['key'],
                type: (string) $arguments['type'],
                config: $config ?? [],
                by: $this->session->user,
            );
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return "Added node [{$arguments['key']}].";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->description('A unique, short identifier for this node, e.g. "send_email".')->required(),
            'type' => $schema->string()->description('The node type, from list_available_nodes.')->required(),
            'config_json' => $schema->string()->description("The node's config as a JSON object string, matching its config_schema from inspect_node_schema."),
        ];
    }
}
