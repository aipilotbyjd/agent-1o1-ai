<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogSearchIncidentsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_search_incidents';
    }

    public function name(): string
    {
        return 'Datadog: Search Incidents';
    }

    public function description(): string
    {
        return 'Searches incidents by state, severity or anything else in the incident search.';
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
                'query' => Field::text('Query', 'Default state:(active OR stable).', 'state:active AND severity:SEV-1'),
                'limit' => Field::integer('Max results', null, 50, 1, 100),
                'offset' => Field::advanced(Field::integer('Skip', 'How many to skip.', 0, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->readIncidents($run, $config, $this->text($config, 'query') ?? 'state:(active OR stable)');
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function readIncidents(Run $run, array $config, string $query): array
    {
        $result = $this->datadog($run, $config, 'GET', '/api/v2/incidents/search', ['query' => $query, 'sort' => '-created', 'page[size]' => $this->limit($config, 50, 100), 'page[offset]' => (int) ($config['offset'] ?? 0)]);
        $incidents = array_map(fn (mixed $row): array => $this->shapeIncident((array) data_get($row, 'data', [])), array_values((array) data_get($result, 'data.attributes.incidents', [])));

        return ['incidents' => $incidents, 'count' => count($incidents), 'total' => data_get($result, 'data.attributes.total')];
    }
}
