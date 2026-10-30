<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListIncidentImpactsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_incident_impacts';
    }

    public function name(): string
    {
        return 'Datadog: List Incident Impacts';
    }

    public function description(): string
    {
        return 'Lists the customer impacts recorded on an incident.';
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
        $impacts = $this->records($this->datadog($run, $config, 'GET', '/api/v2/incidents/'.$this->pathId($config, 'incident_id', 'The incident id').'/impacts'));

        return ['impacts' => $impacts, 'count' => count($impacts)];
    }
}
