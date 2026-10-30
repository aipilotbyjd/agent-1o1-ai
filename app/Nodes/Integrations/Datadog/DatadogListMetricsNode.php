<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListMetricsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_metrics';
    }

    public function name(): string
    {
        return 'Datadog: List Metrics';
    }

    public function description(): string
    {
        return 'Lists the metrics in the account, optionally only those with a tag.';
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
                'tags' => Field::text('Tags', 'Filter by tag, like env:prod.'),
                'metric_type' => Field::advanced(Field::select('Type', ['non_distribution' => 'Non-distribution', 'distribution' => 'Distribution'])),
                'limit' => Field::integer('Max results', null, 100, 1, 10000),
                'cursor' => Field::advanced(Field::text('Page cursor', 'The "next_cursor" of the last call, for the next page.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->datadog($run, $config, 'GET', '/api/v2/metrics', [
            'filter[tags]' => $this->text($config, 'tags'),
            'filter[metric_type]' => $this->text($config, 'metric_type'),
            'page[size]' => $this->limit($config, 100, 10000),
            'page[cursor]' => $this->text($config, 'cursor'),
        ]);
        $metrics = $this->records($result);

        return ['metrics' => $metrics, 'count' => count($metrics), 'next_cursor' => data_get($result, 'meta.pagination.next_cursor')];
    }
}
