<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogTriggerSyntheticsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_trigger_synthetics';
    }

    public function name(): string
    {
        return 'Datadog: Run Synthetic Tests';
    }

    public function description(): string
    {
        return 'Runs Synthetic tests now.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['public_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'public_ids' => Field::list('Test ids', 'Public ids, like abc-def-ghi.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $ids = $this->listOf($config, 'public_ids') ?: throw new InvalidArgumentException('At least one test id is required.');
        $result = $this->datadog($run, $config, 'POST', '/api/v1/synthetics/tests/trigger', [], ['tests' => array_map(fn (string $id): array => ['public_id' => $id], $ids)]);

        return ['batch_id' => $result['batch_id'] ?? null, 'results' => array_values((array) ($result['results'] ?? [])), 'triggered_check_ids' => array_values((array) ($result['triggered_check_ids'] ?? []))];
    }
}
