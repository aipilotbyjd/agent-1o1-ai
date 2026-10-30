<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogAggregateLogsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_aggregate_logs';
    }

    public function name(): string
    {
        return 'Datadog: Aggregate Logs';
    }

    public function description(): string
    {
        return 'Counts or measures logs, optionally grouped by a facet.';
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
                'query' => Field::text('Query', 'Datadog log search.', '*'),
                'from' => Field::text('From', 'Default now-15m.', 'now-15m'),
                'to' => Field::text('To', 'Default now.', 'now'),
                'compute' => Field::json('Compute', 'Default a total count. Like [{"aggregation":"avg","metric":"@duration","type":"total"}].', 'array'),
                'group_by' => Field::json('Group by', 'Like [{"facet":"service","limit":10}].', 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = [
            'filter' => ['from' => $this->text($config, 'from') ?? 'now-15m', 'to' => $this->text($config, 'to') ?? 'now', 'query' => $this->text($config, 'query') ?? '*'],
            'compute' => $this->jsonOf($config, 'compute', 'Compute') ?? [['aggregation' => 'count', 'type' => 'total']],
        ];

        if (($groupBy = $this->jsonOf($config, 'group_by', 'Group by')) !== null) {
            $body['group_by'] = $groupBy;
        }

        $result = $this->datadog($run, $config, 'POST', '/api/v2/logs/analytics/aggregate', [], $body);

        return ['buckets' => array_values((array) data_get($result, 'data.buckets', [])), 'status' => data_get($result, 'meta.status')];
    }
}
