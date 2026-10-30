<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloCreateTaskNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_create_task';
    }

    public function name(): string
    {
        return 'Apollo: Create Task';
    }

    public function description(): string
    {
        return 'Creates a to-do on a contact, like a call or an email.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['user_id', 'contact_id', 'due_at'],
            'properties' => [
                ...$this->credentialFields(),
                'user_id' => Field::dynamic('Assign to', 'apollo.users'),
                'contact_id' => Field::dynamic('Contact', 'apollo.contacts'),
                ...$this->taskTypeField(),
                'due_at' => Field::datetime('Due at'),
                'title' => Field::text('Title'),
                'note' => Field::textarea('Note'),
                'priority' => Field::select('Priority', ['high', 'medium', 'low'], null, 'medium'),
                'status' => Field::advanced(Field::select('Status', ['scheduled', 'completed', 'skipped'], null, 'scheduled')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = ['user_id' => $this->requiredString($config, 'user_id', 'The assignee'), 'contact_id' => $this->requiredString($config, 'contact_id', 'The contact'), 'type' => $config['type'] ?? 'action_item', 'status' => $config['status'] ?? 'scheduled', 'due_at' => $this->requiredString($config, 'due_at', 'The due date'), ...$this->only($config, ['title', 'note', 'priority'])];

        return ['task' => $this->shapeTask((array) ($this->apollo($run, $config, 'POST', '/tasks', [], $body)['task'] ?? []))];
    }
}
