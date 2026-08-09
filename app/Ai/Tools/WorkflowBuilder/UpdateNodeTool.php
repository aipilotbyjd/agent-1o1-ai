<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use JsonException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateNodeTool implements Tool
{
    public function __construct(public readonly WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'update_node';
    }

    public function description(): Stringable|string
    {
        return 'Merge new config fields into an existing node, identified by its key. Only the fields you pass are changed — everything else on the node is kept.';
    }

    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->all();

        try {
            $config = json_decode((string) ($arguments['config_json'] ?? '{}'), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return 'config_json must be a valid JSON object string.';
        }

        try {
            $this->session->updateNode(
                key: (string) $arguments['key'],
                config: $config ?? [],
                by: $this->session->user,
            );
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return "Updated node [{$arguments['key']}].";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->description('The key of the node to update.')->required(),
            'config_json' => $schema->string()->description('The config fields to merge in, as a JSON object string.')->required(),
        ];
    }
}
