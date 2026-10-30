<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryGetOrganizationStatsNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_get_organization_stats';
    }

    public function name(): string
    {
        return 'Sentry: Get Organization Stats';
    }

    public function description(): string
    {
        return 'Gets event counts of an organization over a period, grouped as you choose.';
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
                'group_by' => Field::list('Group by', 'outcome, category, reason, project. Default outcome.'),
                'interval' => Field::advanced(Field::text('Interval', 'Like 1h or 1d.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->sentry($run, $config, 'GET', '/api/0/organizations/'.$this->org($run, $config).'/stats_v2/', [
            'field' => $this->text($config, 'field') ?? 'sum(quantity)',
            'groupBy' => $this->items($config, 'group_by') ?: ['outcome'],
            'statsPeriod' => $this->text($config, 'stats_period') ?? '7d',
            'interval' => $this->text($config, 'interval'),
            'category' => $this->text($config, 'category'),
            'outcome' => $this->text($config, 'outcome'),
        ]);
    }
}
