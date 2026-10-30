<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogGetSyntheticUptimeNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_synthetic_uptime';
    }

    public function name(): string
    {
        return 'Datadog: Get Synthetic Test Uptime';
    }

    public function description(): string
    {
        return 'Gets the uptime of Synthetic tests over a time window.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['public_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'public_ids' => Field::list('Test ids', 'Public ids, like abc-def-ghi.'),
                'from' => Field::text('From', 'Default now-7d.', 'now-7d'),
                'to' => Field::text('To', 'Default now.', 'now'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $ids = $this->listOf($config, 'public_ids') ?: throw new InvalidArgumentException('At least one test id is required.');

        return ['uptimes' => array_values($this->datadog($run, $config, 'POST', '/api/v1/synthetics/tests/uptimes', [], ['public_ids' => $ids, 'from_ts' => $this->unixTime($config['from'] ?? 'now-7d', 'From'), 'to_ts' => $this->unixTime($config['to'] ?? 'now', 'To')]))];
    }
}
