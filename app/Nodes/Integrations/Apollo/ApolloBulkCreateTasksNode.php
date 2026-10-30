<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloBulkCreateTasksNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_bulk_create_tasks';
    }

    public function name(): string
    {
        return 'Apollo: Bulk Create Tasks';
    }

    public function description(): string
    {
        return 'Creates the same to-do on several contacts.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['user_id', 'contact_ids', 'due_at'],
            'properties' => [
                ...$this->credentialFields(),
                'user_id' => Field::dynamic('Assign to', 'apollo.users'),
                'contact_ids' => Field::list('Contact ids'),
                ...$this->taskTypeField(),
                'due_at' => Field::datetime('Due at'),
                'note' => Field::textarea('Note'),
                'priority' => Field::select('Priority', ['high', 'medium', 'low'], null, 'medium'),
                'status' => Field::advanced(Field::select('Status', ['scheduled', 'completed', 'skipped'], null, 'scheduled')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = ['user_id' => $this->requiredString($config, 'user_id', 'The assignee'), 'contact_ids' => $this->requiredList($config, 'contact_ids', 'The contact ids'), 'type' => $config['type'] ?? 'action_item', 'status' => $config['status'] ?? 'scheduled', 'due_at' => $this->requiredString($config, 'due_at', 'The due date'), ...$this->only($config, ['note', 'priority'])];

        return $this->listResult($this->apollo($run, $config, 'POST', '/tasks/bulk_create', [], $body), 'tasks', $this->shapeTask(...));
    }
}
