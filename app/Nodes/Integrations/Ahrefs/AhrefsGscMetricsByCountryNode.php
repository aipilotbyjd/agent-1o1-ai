<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsGscMetricsByCountryNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_gsc_metrics_by_country';
    }

    public function name(): string
    {
        return 'Ahrefs: GSC Metrics by Country';
    }

    public function description(): string
    {
        return 'Clicks from Google Search Console, by country.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['date_from'],
            'properties' => [
                ...$this->credentialFields(),
                'date_from' => Field::text('From', 'The start date of the historical period in YYYY-MM-DD format.'),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
                'portfolio_id' => Field::advanced(Field::integer('Portfolio id', 'Portfolio ID.')),
                'date_to' => Field::text('To', 'The end date of the historical period in YYYY-MM-DD format.'),
                'history_grouping' => Field::advanced(Field::select('History grouping', ['daily', 'weekly', 'monthly'], 'The time interval used to group historical data.', 'monthly')),
                'search_type' => Field::advanced(Field::select('Search type', ['web', 'image', 'video', 'news'], 'The type of search results to return (web, image, video, news).', 'web')),
                'device' => Field::advanced(Field::select('Device', ['desktop', 'mobile', 'tablet'], 'Filter by device type (DESKTOP, MOBILE, TABLET).', null)),
                'where' => Field::advanced(Field::text('Filter', 'A filter in Ahrefs\' syntax, such as {"field":"domain_rating_source","is":["gte",50]}.', '{"field":"domain_rating_source","is":["gte",50]}')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/gsc/metrics-by-country',
            'items' => 'metrics',
            'query' => ['date_from', 'project_id', 'portfolio_id', 'date_to', 'history_grouping', 'search_type', 'device', 'where'],
        ];
    }
}
