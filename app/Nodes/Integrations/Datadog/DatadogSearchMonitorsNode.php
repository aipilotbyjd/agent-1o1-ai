<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogSearchMonitorsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_search_monitors';
    }

    public function name(): string
    {
        return 'Datadog: Search Monitors';
    }

    public function description(): string
    {
        return 'Searches monitors with the query of the Manage Monitors page and returns their status.';
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
                'query' => Field::text('Query', 'Space-separated attributes.', 'type:metric status:alert'),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
                'page' => Field::advanced(Field::integer('Page', 'Starting at 0.', 0, 0)),
                'sort' => Field::advanced(Field::text('Sort', 'Like name,asc or status,desc.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->datadog($run, $config, 'GET', '/api/v1/monitor/search', [
            'query' => $this->text($config, 'query'),
            'page' => (int) ($config['page'] ?? 0),
            'per_page' => $this->limit($config),
            'sort' => $this->text($config, 'sort'),
        ]);
        $monitors = array_values(array_filter((array) ($result['monitors'] ?? []), 'is_array'));

        return ['monitors' => array_map($this->shapeMonitor(...), $monitors), 'count' => count($monitors), 'total' => data_get($result, 'metadata.total_count')];
    }
}
