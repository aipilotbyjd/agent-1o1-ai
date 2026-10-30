<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsGscPerformanceByDeviceNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_gsc_performance_by_device';
    }

    public function name(): string
    {
        return 'Ahrefs: GSC Performance by Device';
    }

    public function description(): string
    {
        return 'Clicks, impressions, CTR and position from Google Search Console, by device.';
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
                'country' => Field::text('Country', 'A two-letter country code (ISO 3166-1 alpha-2).'),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
                'portfolio_id' => Field::advanced(Field::integer('Portfolio id', 'Portfolio ID.')),
                'date_to' => Field::text('To', 'The end date of the historical period in YYYY-MM-DD format.'),
                'search_type' => Field::advanced(Field::select('Search type', ['web', 'image', 'video', 'news'], 'The type of search results to return (web, image, video, news).', 'web')),
                'where' => Field::advanced(Field::text('Filter', 'A filter in Ahrefs\' syntax, such as {"field":"domain_rating_source","is":["gte",50]}.', '{"field":"domain_rating_source","is":["gte",50]}')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/gsc/performance-by-device',
            'items' => 'metrics',
            'query' => ['date_from', 'country', 'project_id', 'portfolio_id', 'date_to', 'search_type', 'where'],
        ];
    }
}
