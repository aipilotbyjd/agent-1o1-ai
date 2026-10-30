<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsGscPageHistoryNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_gsc_page_history';
    }

    public function name(): string
    {
        return 'Ahrefs: GSC Page History';
    }

    public function description(): string
    {
        return 'Clicks, impressions, CTR and position of pages in Google Search Console, over time.';
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
                'history_grouping' => Field::advanced(Field::select('History grouping', ['daily', 'weekly', 'monthly'], 'The time interval used to group historical data.', 'monthly')),
                'device' => Field::advanced(Field::select('Device', ['desktop', 'mobile', 'tablet'], 'Filter by device type (DESKTOP, MOBILE, TABLET).', null)),
                'pages' => Field::advanced(Field::text('Pages', 'A comma-separated list of page URLs to get history data for.')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/gsc/page-history',
            'items' => 'metrics',
            'query' => ['date_from', 'country', 'project_id', 'portfolio_id', 'date_to', 'history_grouping', 'device', 'pages'],
        ];
    }
}
