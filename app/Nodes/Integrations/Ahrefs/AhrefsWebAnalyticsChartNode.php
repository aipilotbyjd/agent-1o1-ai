<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsWebAnalyticsChartNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_web_analytics_chart';
    }

    public function name(): string
    {
        return 'Ahrefs: Web Analytics Chart';
    }

    public function description(): string
    {
        return 'Visitors, visits and pageviews of a project over time.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['granularity', 'project_id'],
            'properties' => [
                ...$this->credentialFields(),
                'granularity' => Field::select('Granularity', ['hourly', 'daily', 'weekly', 'monthly'], 'Time granularity for chart data points.', null),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
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
            'path' => '/web-analytics/chart',
            'items' => 'points',
            'query' => ['granularity', 'project_id', 'where', 'to', 'from'],
        ];
    }
}
