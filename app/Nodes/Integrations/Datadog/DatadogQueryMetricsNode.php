<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogQueryMetricsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_query_metrics';
    }

    public function name(): string
    {
        return 'Datadog: Query Metrics';
    }

    public function description(): string
    {
        return 'Queries timeseries points for a metric query.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::textarea('Query', null, 'avg:system.cpu.user{*}'),
                'from' => Field::text('From', 'Default now-1h. A date, Unix seconds, or relative like now-1d.', 'now-1h'),
                'to' => Field::text('To', 'Default now.', 'now'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->datadog($run, $config, 'GET', '/api/v1/query', [
            'query' => $this->requiredString($config, 'query', 'The query'),
            'from' => $this->unixTime($config['from'] ?? 'now-1h', 'From'),
            'to' => $this->unixTime($config['to'] ?? 'now', 'To'),
        ]);

        return ['query' => $result['query'] ?? null, 'series' => array_values((array) ($result['series'] ?? [])), 'count' => count((array) ($result['series'] ?? [])), 'status' => $result['status'] ?? null];
    }
}
