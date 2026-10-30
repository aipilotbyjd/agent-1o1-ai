<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsPagesHistoryNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_pages_history';
    }

    public function name(): string
    {
        return 'Ahrefs: Pages History';
    }

    public function description(): string
    {
        return 'The number of pages of a domain or URL over time.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['target', 'date_from'],
            'properties' => [
                ...$this->credentialFields(),
                'target' => Field::text('Target', 'The target of the search: a domain or a URL.'),
                'date_from' => Field::text('From', 'The start date of the historical period in YYYY-MM-DD format.'),
                'country' => Field::text('Country', 'A two-letter country code (ISO 3166-1 alpha-2).'),
                'mode' => Field::advanced(Field::select('Scope', ['exact', 'prefix', 'domain', 'subdomains'], 'The scope of the target.', 'subdomains')),
                'portfolio_id' => Field::advanced(Field::integer('Portfolio id', 'The unique identifier of the portfolio.')),
                'protocol' => Field::advanced(Field::select('Protocol', ['both', 'http', 'https'], 'The protocol of your target.', 'both')),
                'date_to' => Field::text('To', 'The end date of the historical period in YYYY-MM-DD format.'),
                'history_grouping' => Field::advanced(Field::select('History grouping', ['daily', 'weekly', 'monthly'], 'The time interval used to group historical data.', 'monthly')),
                'page_positions' => Field::advanced(Field::select('Page positions', ['top10', 'top100'], 'Filter pages by their ranking position.', 'top100')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/site-explorer/pages-history',
            'items' => 'pages',
            'query' => ['target', 'date_from', 'country', 'mode', 'portfolio_id', 'protocol', 'date_to', 'history_grouping', 'page_positions'],
        ];
    }
}
