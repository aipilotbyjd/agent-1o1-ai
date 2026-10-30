<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogTakeGraphSnapshotNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_take_graph_snapshot';
    }

    public function name(): string
    {
        return 'Datadog: Take Graph Snapshot';
    }

    public function description(): string
    {
        return 'Makes an image of a metric graph and returns its address.';
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
                'metric_query' => Field::text('Metric query', null, 'avg:system.load.1{*}'),
                'start' => Field::text('From', 'Default now-1h.', 'now-1h'),
                'end' => Field::text('To', 'Default now.', 'now'),
                'title' => Field::text('Title'),
                'event_query' => Field::advanced(Field::text('Event overlay query')),
                'graph_def' => Field::advanced(Field::textarea('Graph definition', 'JSON, used instead of the metric query.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->datadog($run, $config, 'GET', '/api/v1/graph/snapshot', [
            'metric_query' => $this->text($config, 'metric_query'),
            'graph_def' => $this->text($config, 'graph_def'),
            'event_query' => $this->text($config, 'event_query'),
            'title' => $this->text($config, 'title'),
            'start' => $this->unixTime($config['start'] ?? 'now-1h', 'From'),
            'end' => $this->unixTime($config['end'] ?? 'now', 'To'),
        ]);

        return ['url' => $result['snapshot_url'] ?? null, 'metric_query' => $result['metric_query'] ?? null];
    }
}
