<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogSearchSlosNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_search_slos';
    }

    public function name(): string
    {
        return 'Datadog: Search SLOs';
    }

    public function description(): string
    {
        return 'Searches service level objectives.';
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
                'query' => Field::text('Query', null, 'service:web'),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
                'page' => Field::advanced(Field::integer('Page', 'Starting at 0.', 0, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->datadog($run, $config, 'GET', '/api/v1/slo/search', ['query' => $this->text($config, 'query'), 'page[size]' => $this->limit($config), 'page[number]' => (int) ($config['page'] ?? 0)]);
        $slos = array_map($this->flatten(...), array_values(array_filter((array) data_get($result, 'data.attributes.slos', []), 'is_array')));

        return ['slos' => array_map(fn (array $slo): array => isset($slo['data']) ? $this->flatten((array) $slo['data']) : $slo, $slos), 'count' => count($slos), 'total' => data_get($result, 'meta.pagination.total')];
    }
}
