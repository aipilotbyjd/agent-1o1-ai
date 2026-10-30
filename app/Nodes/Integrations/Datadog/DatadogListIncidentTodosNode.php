<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListIncidentTodosNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_incident_todos';
    }

    public function name(): string
    {
        return 'Datadog: List Incident Todos';
    }

    public function description(): string
    {
        return 'Lists an incident\'s todos.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['incident_id'],
            'properties' => [
                ...$this->credentialFields(),
                'incident_id' => Field::text('Incident id', 'From List Incidents.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $todos = $this->records($this->datadog($run, $config, 'GET', '/api/v2/incidents/'.$this->pathId($config, 'incident_id', 'The incident id').'/relationships/todos'));

        return ['todos' => $todos, 'count' => count($todos)];
    }
}
