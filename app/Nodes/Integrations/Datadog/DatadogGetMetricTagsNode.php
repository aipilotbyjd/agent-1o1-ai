<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogGetMetricTagsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_metric_tags';
    }

    public function name(): string
    {
        return 'Datadog: Get Metric Tags';
    }

    public function description(): string
    {
        return 'Lists the tags a metric reports with.';
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
        $result = $this->find($run, $config, '/api/v2/metrics/'.$this->pathId($config, 'metric', 'The metric').'/all-tags');

        return $result === null ? ['found' => false] : ['found' => true, 'metric' => data_get($result, 'data.id'), 'tags' => array_values((array) data_get($result, 'data.attributes.tags', []))];
    }
}
