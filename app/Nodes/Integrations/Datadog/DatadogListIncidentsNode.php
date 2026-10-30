<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListIncidentsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_incidents';
    }

    public function name(): string
    {
        return 'Datadog: List Incidents';
    }

    public function description(): string
    {
        return 'Lists incidents.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'limit' => Field::integer('Max results', null, 50, 1, 100),
                'offset' => Field::advanced(Field::integer('Skip', 'How many to skip.', 0, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->datadog($run, $config, 'GET', '/api/v2/incidents', ['page[size]' => $this->limit($config, 50, 100), 'page[offset]' => (int) ($config['offset'] ?? 0)]);
        $incidents = array_map($this->shapeIncident(...), array_values(array_filter((array) ($result['data'] ?? []), 'is_array')));

        return ['incidents' => $incidents, 'count' => count($incidents), 'next_offset' => data_get($result, 'meta.pagination.next_offset')];
    }
}
