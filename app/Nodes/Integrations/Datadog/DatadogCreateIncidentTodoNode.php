<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogCreateIncidentTodoNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_create_incident_todo';
    }

    public function name(): string
    {
        return 'Datadog: Create Incident Todo';
    }

    public function description(): string
    {
        return 'Adds a todo to an incident.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['incident_id', 'content'],
            'properties' => [
                ...$this->credentialFields(),
                'incident_id' => Field::text('Incident id', 'From List Incidents.'),
                'content' => Field::textarea('Todo'),
                'assignees' => Field::list('Assignees', 'Handles, like @user@example.com.'),
                'due_date' => Field::advanced(Field::datetime('Due')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $attributes = ['content' => $this->requiredString($config, 'content', 'The todo'), 'assignees' => $this->listOf($config, 'assignees')];

        if (filled($config['due_date'] ?? null)) {
            $attributes['due_date'] = (string) $config['due_date'];
        }

        return $this->flatten((array) ($this->datadog($run, $config, 'POST', '/api/v2/incidents/'.$this->pathId($config, 'incident_id', 'The incident id').'/relationships/todos', [], ['data' => ['type' => 'incident_todos', 'attributes' => $attributes]])['data'] ?? []));
    }
}
