<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsWebAnalyticsStatsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_web_analytics_stats';
    }

    public function name(): string
    {
        return 'Ahrefs: Web Analytics Stats';
    }

    public function description(): string
    {
        return 'Visitors, visits, pageviews and bounce rate of a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
                'limit' => Field::integer('Limit', 'The most rows to return. Ahrefs charges per row.', 100, 1, 100000),
                'order_by' => Field::advanced(Field::text('Sort', 'Columns with optional direction, such as traffic:desc.', 'traffic:desc')),
                'where' => Field::advanced(Field::text('Filter', 'A filter in Ahrefs\' syntax, such as {"field":"domain_rating_source","is":["gte",50]}.', '{"field":"domain_rating_source","is":["gte",50]}')),
                'to' => Field::text('To', 'end datetime for the data query'),
                'from' => Field::text('From', 'start datetime for the data query'),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/web-analytics/stats',
            'items' => 'stats',
            'query' => ['project_id', 'limit', 'order_by', 'where', 'to', 'from'],
            'limited' => true,
        ];
    }
}
