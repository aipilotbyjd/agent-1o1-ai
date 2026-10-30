<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloUpdateTaskNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_update_task';
    }

    public function name(): string
    {
        return 'Apollo: Update Task';
    }

    public function description(): string
    {
        return 'Changes the fields you fill in on a task.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['task_id'],
            'properties' => [
                ...$this->credentialFields(),
                'task_id' => Field::text('Task id'),
                'title' => Field::text('Title'),
                'note' => Field::textarea('Note'),
                'due_at' => Field::datetime('Due at'),
                'user_id' => Field::dynamic('Assign to', 'apollo.users'),
                ...$this->taskTypeField(null),
                'priority' => Field::select('Priority', ['high', 'medium', 'low']),
                'status' => Field::advanced(Field::select('Status', ['scheduled', 'completed', 'skipped'])),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->apollo($run, $config, 'PATCH', '/tasks/'.$this->recordId($config, 'task_id', 'The task id'), [], $this->only($config, ['title', 'note', 'due_at', 'user_id', 'type', 'priority', 'status']));

        return ['task' => $this->shapeTask((array) ($body['task'] ?? []))];
    }
}
