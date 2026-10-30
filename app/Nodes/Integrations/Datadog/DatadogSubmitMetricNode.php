<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogSubmitMetricNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_submit_metric';
    }

    public function name(): string
    {
        return 'Datadog: Submit Metric';
    }

    public function description(): string
    {
        return 'Sends a custom metric data point to Datadog.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'metric' => Field::text('Metric', null, 'my.app.orders'),
                'value' => Field::text('Value', 'A number.'),
                'type' => Field::select('Type', ['gauge' => 'Gauge', 'count' => 'Count', 'rate' => 'Rate'], null, 'gauge'),
                'tags' => Field::list('Tags', 'Like env:prod.'),
                'host' => Field::text('Host'),
                'timestamp' => Field::advanced(Field::datetime('Time', 'Default now.')),
                'unit' => Field::advanced(Field::text('Unit', null, 'byte')),
                'interval' => Field::advanced(Field::integer('Interval', 'Seconds, for a count or rate.', null, 1)),
                'series' => Field::advanced(Field::json('Raw series', 'Several points or metrics at once, as in the API. Used instead of the fields above.', 'array')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $series = $this->jsonOf($config, 'series', 'Series');

        if ($series === null) {
            $value = $config['value'] ?? null;

            if (! is_numeric($value)) {
                throw new InvalidArgumentException('The value must be a number.');
            }

            $types = ['count' => 1, 'rate' => 2, 'gauge' => 3];
            $series = [array_filter([
                'metric' => $this->requiredString($config, 'metric', 'The metric'),
                'type' => $types[$config['type'] ?? 'gauge'] ?? 3,
                'points' => [['timestamp' => filled($config['timestamp'] ?? null) ? $this->unixTime($config['timestamp'], 'The time') : time(), 'value' => (float) $value]],
                'tags' => $this->listOf($config, 'tags') ?: null,
                'unit' => $this->text($config, 'unit'),
                'interval' => filled($config['interval'] ?? null) ? (int) $config['interval'] : null,
                'resources' => filled($config['host'] ?? null) ? [['name' => (string) $config['host'], 'type' => 'host']] : null,
            ], fn (mixed $item): bool => $item !== null)];
        }

        $result = $this->datadog($run, $config, 'POST', '/api/v2/series', [], ['series' => $series]);

        return ['accepted' => true, 'errors' => array_values((array) ($result['errors'] ?? []))];
    }
}
