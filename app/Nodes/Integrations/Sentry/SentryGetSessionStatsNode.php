<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryGetSessionStatsNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_get_session_stats';
    }

    public function name(): string
    {
        return 'Sentry: Get Release Health';
    }

    public function description(): string
    {
        return 'Gets session statistics, such as crash-free rate, across releases.';
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
                'fields' => Field::list('Fields', 'Default sum(session). Also crash_free_rate(session), count_unique(user).'),
                'group_by' => Field::list('Group by', 'project, release, environment or session.status.'),
                'query' => Field::text('Search', null, 'release:backend@1.2.3'),
                'stats_period' => Field::text('Period', 'Default 24h.', '24h'),
                'project_ids' => Field::advanced(Field::list('Project ids', 'Numeric ids. Default your projects.')),
                'environment' => Field::advanced(Field::text('Environment')),
                'interval' => Field::advanced(Field::text('Interval', 'Like 1h or 1d.')),
                'order_by' => Field::advanced(Field::text('Order by')),
                'limit' => Field::advanced(Field::integer('Max groups', null, null, 1, 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $fields = $this->items($config, 'fields') ?: ['sum(session)'];

        return $this->sentry($run, $config, 'GET', '/api/0/organizations/'.$this->org($run, $config).'/sessions/', [
            'field' => $fields,
            'groupBy' => $this->items($config, 'group_by'),
            'query' => $this->text($config, 'query'),
            'project' => $this->items($config, 'project_ids'),
            'environment' => $this->text($config, 'environment'),
            'statsPeriod' => $this->text($config, 'stats_period') ?? '24h',
            'interval' => $this->text($config, 'interval'),
            'orderBy' => $this->text($config, 'order_by'),
            'per_page' => filled($config['limit'] ?? null) ? $this->limit($config) : null,
        ]);
    }
}
