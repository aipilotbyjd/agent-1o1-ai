<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryDeleteCronMonitorNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_delete_cron_monitor';
    }

    public function name(): string
    {
        return 'Sentry: Delete Cron Monitor';
    }

    public function description(): string
    {
        return 'Deletes a cron monitor.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['cron'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'cron' => Field::text('Cron monitor', 'Its slug or id.', 'nightly-job'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $cron = $this->pathId($config, 'cron', 'The cron monitor');
        $this->sentry($run, $config, 'DELETE', '/api/0/organizations/'.$this->org($run, $config)."/monitors/{$cron}/");

        return ['deleted' => true, 'cron' => $cron];
    }
}
