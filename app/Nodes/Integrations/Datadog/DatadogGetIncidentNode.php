<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogGetIncidentNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_incident';
    }

    public function name(): string
    {
        return 'Datadog: Get Incident';
    }

    public function description(): string
    {
        return 'Gets an incident.';
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
        $incident = $this->find($run, $config, '/api/v2/incidents/'.$this->pathId($config, 'incident_id', 'The incident id'));

        return $incident === null ? ['found' => false] : ['found' => true, ...$this->shapeIncident((array) ($incident['data'] ?? []))];
    }
}
