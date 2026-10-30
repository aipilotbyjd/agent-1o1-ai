<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryListCronCheckinsNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_cron_checkins';
    }

    public function name(): string
    {
        return 'Sentry: List Cron Check-Ins';
    }

    public function description(): string
    {
        return 'Lists the check-ins of a cron monitor.';
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
                'cursor' => Field::advanced(Field::text('Page cursor', 'The "next_cursor" of the last call, for the next page.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/monitors/'.$this->pathId($config, 'cron', 'The cron monitor').'/checkins/', 'checkins');
    }
}
