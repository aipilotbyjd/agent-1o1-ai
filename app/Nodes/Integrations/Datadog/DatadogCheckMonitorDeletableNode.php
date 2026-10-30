<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogCheckMonitorDeletableNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_check_monitor_deletable';
    }

    public function name(): string
    {
        return 'Datadog: Check Monitor Can Be Deleted';
    }

    public function description(): string
    {
        return 'Says whether monitors can be deleted, or what still uses them.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['monitor_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'monitor_ids' => Field::list('Monitor ids'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $ids = $this->listOf($config, 'monitor_ids');

        if ($ids === [] || array_filter($ids, fn (string $id): bool => ! ctype_digit($id)) !== []) {
            throw new InvalidArgumentException('Monitor ids must be numbers.');
        }

        $response = $this->datadogResponse($run, $config, 'GET', '/api/v1/monitor/can_delete', ['monitor_ids' => implode(',', $ids)]);

        if ($response->status() !== 409) {
            $this->assertOk($response);
        }

        return ['ok' => array_map('strval', (array) ($response->json('data.ok') ?? [])), 'errors' => (array) ($response->json('errors') ?? [])];
    }
}
