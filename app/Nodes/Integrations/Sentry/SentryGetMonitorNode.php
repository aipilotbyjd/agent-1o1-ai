<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryGetMonitorNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_get_monitor';
    }

    public function name(): string
    {
        return 'Sentry: Get Monitor';
    }

    public function description(): string
    {
        return 'Gets a monitor.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['monitor_id'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'monitor_id' => Field::text('Monitor id', 'From List Monitors.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $monitor = $this->find($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/detectors/'.$this->pathId($config, 'monitor_id', 'The monitor id').'/');

        return $monitor === null ? ['found' => false] : ['found' => true, ...$monitor];
    }
}
