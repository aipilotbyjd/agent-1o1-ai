<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryGetCronMonitorNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_get_cron_monitor';
    }

    public function name(): string
    {
        return 'Sentry: Get Cron Monitor';
    }

    public function description(): string
    {
        return 'Gets a cron monitor.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        $monitor = $this->find($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/monitors/'.$this->pathId($config, 'cron', 'The cron monitor').'/');

        return $monitor === null ? ['found' => false] : ['found' => true, ...$monitor];
    }
}
