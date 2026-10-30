<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryDeleteMonitorNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_delete_monitor';
    }

    public function name(): string
    {
        return 'Sentry: Delete Monitor';
    }

    public function description(): string
    {
        return 'Deletes a monitor.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $id = $this->pathId($config, 'monitor_id', 'The monitor id');
        $this->sentry($run, $config, 'DELETE', '/api/0/organizations/'.$this->org($run, $config)."/detectors/{$id}/");

        return ['deleted' => true, 'id' => $id];
    }
}
