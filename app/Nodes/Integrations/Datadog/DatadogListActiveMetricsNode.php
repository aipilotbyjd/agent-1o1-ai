<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListActiveMetricsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_active_metrics';
    }

    public function name(): string
    {
        return 'Datadog: List Active Metrics';
    }

    public function description(): string
    {
        return 'Lists the metrics that reported since a given time.';
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
                'from' => Field::text('Since', 'Default now-1d. A date, Unix seconds, or relative.', 'now-1d'),
                'host' => Field::text('Host'),
                'tag_filter' => Field::text('Tag filter', 'Like env IN (staging,test) AND service:web.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->datadog($run, $config, 'GET', '/api/v1/metrics', [
            'from' => $this->unixTime($config['from'] ?? 'now-1d', 'From'),
            'host' => $this->text($config, 'host'),
            'tag_filter' => $this->text($config, 'tag_filter'),
        ]);
        $metrics = array_values((array) ($result['metrics'] ?? []));

        return ['metrics' => $metrics, 'count' => count($metrics)];
    }
}
