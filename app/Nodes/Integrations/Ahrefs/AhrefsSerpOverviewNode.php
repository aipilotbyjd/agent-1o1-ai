<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsSerpOverviewNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_serp_overview';
    }

    public function name(): string
    {
        return 'Ahrefs: SERP Overview';
    }

    public function description(): string
    {
        return 'The top search results for a keyword, with their metrics.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['keyword', 'country'],
            'properties' => [
                ...$this->credentialFields(),
                'keyword' => Field::text('Keyword', 'The keyword to return SERP Overview for.'),
                'country' => Field::text('Country', 'A two-letter country code (ISO 3166-1 alpha-2).'),
                'date' => Field::text('Date', 'A timestamp on which the last available SERP Overview is returned in YYYY-MM-DDThh:mm:ss format.'),
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'position,url,title,type,domain_rating,url_rating,backlinks,refdomains,traffic,keywords,top_keyword,top_keyword_volume,update_date')),
                'top_positions' => Field::advanced(Field::integer('Top positions', 'The number of top organic SERP positions to return.')),
                'type' => Field::advanced(Field::text('Type', 'A comma-separated list of SERP feature types to filter by.')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/serp-overview/serp-overview',
            'items' => 'positions',
            'select' => 'position,url,title,type,domain_rating,url_rating,backlinks,refdomains,traffic,keywords,top_keyword,top_keyword_volume,update_date',
            'query' => ['keyword', 'country', 'date', 'select', 'top_positions', 'type'],
        ];
    }
}
