<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryGetStatsByProjectNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_get_stats_by_project';
    }

    public function name(): string
    {
        return 'Sentry: Get Stats By Project';
    }

    public function description(): string
    {
        return 'Gets an organization\'s event counts for each project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'field' => Field::select('Measure', ['sum(quantity)' => 'Quantity', 'sum(times_seen)' => 'Times seen'], null, 'sum(quantity)'),
                'stats_period' => Field::text('Period', 'Like 24h, 7d or 14d.', '7d'),
                'category' => Field::select('Category', ['error' => 'Errors', 'transaction' => 'Transactions', 'attachment' => 'Attachments', 'replay' => 'Replays', 'profile' => 'Profiles', 'monitor' => 'Cron check-ins']),
                'outcome' => Field::advanced(Field::select('Outcome', ['accepted' => 'Accepted', 'filtered' => 'Filtered', 'rate_limited' => 'Rate limited', 'invalid' => 'Invalid', 'abuse' => 'Abuse', 'client_discard' => 'Client discard', 'cardinality_limited' => 'Cardinality limited'])),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->sentry($run, $config, 'GET', '/api/0/organizations/'.$this->org($run, $config).'/stats-summary/', [
            'field' => $this->text($config, 'field') ?? 'sum(quantity)',
            'statsPeriod' => $this->text($config, 'stats_period') ?? '7d',
            'category' => $this->text($config, 'category'),
            'outcome' => $this->text($config, 'outcome'),
        ]);
    }
}
