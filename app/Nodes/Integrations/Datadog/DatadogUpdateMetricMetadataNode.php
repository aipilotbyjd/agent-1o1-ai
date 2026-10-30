<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogUpdateMetricMetadataNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_update_metric_metadata';
    }

    public function name(): string
    {
        return 'Datadog: Update Metric Metadata';
    }

    public function description(): string
    {
        return 'Changes a metric\'s description, type or unit.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['metric'],
            'properties' => [
                ...$this->credentialFields(),
                'metric' => Field::text('Metric', null, 'system.cpu.user'),
                'description' => Field::textarea('Description'),
                'short_name' => Field::text('Short name'),
                'type' => Field::select('Type', ['gauge' => 'Gauge', 'count' => 'Count', 'rate' => 'Rate']),
                'unit' => Field::text('Unit', null, 'byte'),
                'per_unit' => Field::advanced(Field::text('Per unit', null, 'second')),
                'statsd_interval' => Field::advanced(Field::integer('StatsD interval', 'Seconds.', null, 1)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $metric = $this->pathId($config, 'metric', 'The metric');
        $body = array_filter(['description' => $this->text($config, 'description'), 'short_name' => $this->text($config, 'short_name'), 'type' => $this->text($config, 'type'), 'unit' => $this->text($config, 'unit'), 'per_unit' => $this->text($config, 'per_unit'), 'statsd_interval' => filled($config['statsd_interval'] ?? null) ? (int) $config['statsd_interval'] : null], fn (mixed $value): bool => $value !== null);

        if ($body === []) {
            throw new InvalidArgumentException('Fill in something to change.');
        }

        return $this->datadog($run, $config, 'PUT', "/api/v1/metrics/{$metric}", [], $body);
    }
}
