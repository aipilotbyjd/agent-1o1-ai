<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DisconnectNodesTool implements Tool
{
    public function __construct(public readonly WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'disconnect_nodes';
    }

    public function description(): Stringable|string
    {
        return 'Remove the edge between two nodes.';
    }

    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->all();

        $this->session->disconnect(
            from: (string) $arguments['from'],
            to: (string) $arguments['to'],
            by: $this->session->user,
        );

        return "Disconnected [{$arguments['from']}] from [{$arguments['to']}].";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('The key of the source node.')->required(),
            'to' => $schema->string()->description('The key of the target node.')->required(),
        ];
    }
}
