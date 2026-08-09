<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RemoveNodeTool implements Tool
{
    public function __construct(public readonly WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'remove_node';
    }

    public function description(): Stringable|string
    {
        return 'Remove a node from the draft by its key. Any edges connected to it are removed too.';
    }

    public function handle(Request $request): Stringable|string
    {
        $key = (string) ($request->all()['key'] ?? '');

        try {
            $this->session->removeNode($key, by: $this->session->user);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return "Removed node [{$key}].";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->description('The key of the node to remove.')->required(),
        ];
    }
}
