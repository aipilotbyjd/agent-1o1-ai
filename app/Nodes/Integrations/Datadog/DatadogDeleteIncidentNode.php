<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogDeleteIncidentNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_delete_incident';
    }

    public function name(): string
    {
        return 'Datadog: Delete Incident';
    }

    public function description(): string
    {
        return 'Deletes an incident.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $id = $this->pathId($config, 'incident_id', 'The incident id');
        $this->datadog($run, $config, 'DELETE', "/api/v2/incidents/{$id}");

        return ['deleted' => true, 'id' => rawurldecode($id)];
    }
}
