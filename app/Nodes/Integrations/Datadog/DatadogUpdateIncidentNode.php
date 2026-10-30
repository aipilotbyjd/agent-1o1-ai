<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogUpdateIncidentNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_update_incident';
    }

    public function name(): string
    {
        return 'Datadog: Update Incident';
    }

    public function description(): string
    {
        return 'Changes an incident\'s title, state, severity or impact.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['incident_id'],
            'properties' => [
                ...$this->credentialFields(),
                'incident_id' => Field::text('Incident id', 'From List Incidents.'),
                'title' => Field::text('Title'),
                'severity' => Field::select('Severity', ['SEV-1' => 'SEV-1', 'SEV-2' => 'SEV-2', 'SEV-3' => 'SEV-3', 'SEV-4' => 'SEV-4', 'SEV-5' => 'SEV-5', 'UNKNOWN' => 'Unknown']),
                'state' => Field::select('State', ['active' => 'Active', 'stable' => 'Stable', 'resolved' => 'Resolved']),
                'customer_impacted' => Field::advanced(Field::boolean('Customers are affected')),
                'customer_impact_scope' => Field::advanced(Field::text('What customers see')),
                'fields' => Field::advanced(Field::json('More fields', 'As in the API.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $fields = (array) ($this->jsonOf($config, 'fields', 'Fields') ?? []);

        foreach (['state', 'severity'] as $name) {
            if (filled($config[$name] ?? null)) {
                $fields[$name] = ['type' => 'dropdown', 'value' => (string) $config[$name]];
            }
        }

        $id = $this->pathId($config, 'incident_id', 'The incident id');
        $attributes = array_filter([
            'title' => $this->text($config, 'title'),
            'customer_impacted' => isset($config['customer_impacted']) && $config['customer_impacted'] !== '' ? (bool) $config['customer_impacted'] : null,
            'customer_impact_scope' => $this->text($config, 'customer_impact_scope'),
            'fields' => $fields ?: null,
        ], fn (mixed $value): bool => $value !== null);

        if ($attributes === []) {
            throw new InvalidArgumentException('Fill in something to change.');
        }

        return $this->shapeIncident((array) ($this->datadog($run, $config, 'PATCH', "/api/v2/incidents/{$id}", [], ['data' => ['type' => 'incidents', 'id' => rawurldecode($id), 'attributes' => $attributes]])['data'] ?? []));
    }
}
