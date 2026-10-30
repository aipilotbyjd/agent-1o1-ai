<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogSubmitDistributionNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_submit_distribution';
    }

    public function name(): string
    {
        return 'Datadog: Submit Distribution Points';
    }

    public function description(): string
    {
        return 'Sends distribution metric values to Datadog, for percentiles across hosts.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['metric', 'values'],
            'properties' => [
                ...$this->credentialFields(),
                'metric' => Field::text('Metric'),
                'values' => Field::list('Values', 'Numbers.'),
                'tags' => Field::list('Tags', 'Like env:prod.'),
                'host' => Field::text('Host'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $values = array_map(fn (string $value): float => is_numeric($value) ? (float) $value : throw new InvalidArgumentException('Each value must be a number.'), $this->listOf($config, 'values'));

        if ($values === []) {
            throw new InvalidArgumentException('At least one value is required.');
        }

        $series = array_filter([
            'metric' => $this->requiredString($config, 'metric', 'The metric'),
            'points' => [[time(), $values]],
            'tags' => $this->listOf($config, 'tags') ?: null,
            'host' => $this->text($config, 'host'),
        ], fn (mixed $item): bool => $item !== null);
        $result = $this->datadog($run, $config, 'POST', '/api/v1/distribution_points', [], ['series' => [$series]]);

        return ['accepted' => true, 'status' => $result['status'] ?? null];
    }
}
