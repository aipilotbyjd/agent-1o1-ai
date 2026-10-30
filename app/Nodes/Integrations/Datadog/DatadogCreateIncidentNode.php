<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogCreateIncidentNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_create_incident';
    }

    public function name(): string
    {
        return 'Datadog: Create Incident';
    }

    public function description(): string
    {
        return 'Declares an incident.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title'],
            'properties' => [
                ...$this->credentialFields(),
                'title' => Field::text('Title'),
                'severity' => Field::select('Severity', ['SEV-1' => 'SEV-1', 'SEV-2' => 'SEV-2', 'SEV-3' => 'SEV-3', 'SEV-4' => 'SEV-4', 'SEV-5' => 'SEV-5', 'UNKNOWN' => 'Unknown']),
                'state' => Field::select('State', ['active' => 'Active', 'stable' => 'Stable', 'resolved' => 'Resolved']),
                'customer_impacted' => Field::boolean('Customers are affected'),
                'customer_impact_scope' => Field::text('What customers see', 'Needed when customers are affected.'),
                'commander_id' => $this->userField('Commander'),
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

        $attributes = array_filter([
            'title' => $this->requiredString($config, 'title', 'The title'),
            'customer_impacted' => (bool) ($config['customer_impacted'] ?? false),
            'customer_impact_scope' => $this->text($config, 'customer_impact_scope'),
            'fields' => $fields ?: null,
        ], fn (mixed $value): bool => $value !== null);
        $data = ['type' => 'incidents', 'attributes' => $attributes];

        if (filled($config['commander_id'] ?? null)) {
            $data['relationships'] = ['commander_user' => ['data' => ['id' => $this->pathId($config, 'commander_id', 'The commander'), 'type' => 'users']]];
        }

        return $this->shapeIncident((array) ($this->datadog($run, $config, 'POST', '/api/v2/incidents', [], ['data' => $data])['data'] ?? []));
    }
}
