<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Ai\Tools\WorkflowBuilder\Concerns\EditsDraft;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DisconnectNodesTool implements Tool
{
    use EditsDraft;

    public function __construct(public readonly WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'disconnect_nodes';
    }

    public function description(): Stringable|string
    {
        return "Remove the edges from one node to another. Pass 'condition' to remove only the edge with that condition (\"none\" for the unconditional one) and keep the rest — e.g. to turn an always-edge into an error path, disconnect with condition \"none\", then connect with condition \"error\".";
    }

    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->all();

        $condition = isset($arguments['condition']) && $arguments['condition'] !== '' ? (string) $arguments['condition'] : null;

        return $this->attemptEdit(fn () => $this->session->disconnect(
            from: (string) $arguments['from'],
            to: (string) $arguments['to'],
            by: $this->session->user,
            condition: $condition === 'none' ? null : $condition,
            onlyCondition: $condition !== null,
        ), "Disconnected [{$arguments['from']}] from [{$arguments['to']}].");
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('The key of the source node.')->required(),
            'to' => $schema->string()->description('The key of the target node.')->required(),
            'condition' => $schema->string()->description('Optional: remove only the edge with this condition ("none" for the unconditional edge).'),
        ];
    }
}
