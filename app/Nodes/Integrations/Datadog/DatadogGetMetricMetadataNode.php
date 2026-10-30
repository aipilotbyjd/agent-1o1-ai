<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogGetMetricMetadataNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_metric_metadata';
    }

    public function name(): string
    {
        return 'Datadog: Get Metric Metadata';
    }

    public function description(): string
    {
        return 'Gets a metric\'s description, type and unit.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['metric'],
            'properties' => [
                ...$this->credentialFields(),
                'metric' => Field::text('Metric', null, 'system.cpu.user'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $metric = $this->find($run, $config, '/api/v1/metrics/'.$this->pathId($config, 'metric', 'The metric'));

        return $metric === null ? ['found' => false] : ['found' => true, ...$metric];
    }
}
