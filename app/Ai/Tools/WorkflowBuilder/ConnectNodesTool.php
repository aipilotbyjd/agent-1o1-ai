<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Ai\Tools\WorkflowBuilder\Concerns\EditsDraft;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ConnectNodesTool implements Tool
{
    use EditsDraft;

    public function __construct(public readonly WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'connect_nodes';
    }

    public function description(): Stringable|string
    {
        return "Add an edge from one node to another. Both nodes must already exist. Use 'condition' for branching nodes (e.g. a router's branch value) or \"error\" to handle a failure from the source node. An edge's condition can't be changed in place: to make an existing edge conditional, remove the old one with disconnect_nodes first — otherwise both edges fire.";
    }

    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->all();

        return $this->attemptEdit(fn () => $this->session->connect(
            from: (string) $arguments['from'],
            to: (string) $arguments['to'],
            condition: isset($arguments['condition']) && $arguments['condition'] !== '' ? (string) $arguments['condition'] : null,
            by: $this->session->user,
        ), "Connected [{$arguments['from']}] to [{$arguments['to']}].");
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('The key of the source node.')->required(),
            'to' => $schema->string()->description('The key of the target node.')->required(),
            'condition' => $schema->string()->description('Optional branch condition this edge fires on, or "error" to handle a failure.'),
        ];
    }
}
