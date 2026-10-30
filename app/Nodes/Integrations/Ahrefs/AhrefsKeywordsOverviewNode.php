<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsKeywordsOverviewNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_keywords_overview';
    }

    public function name(): string
    {
        return 'Ahrefs: Keywords Overview';
    }

    public function description(): string
    {
        return 'Volume, difficulty, cost per click and traffic potential of keywords.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['country'],
            'properties' => [
                ...$this->credentialFields(),
                'target' => Field::text('Target', 'The target of the search: a domain or a URL.'),
                'country' => Field::text('Country', 'A two-letter country code (ISO 3166-1 alpha-2).'),
                'limit' => Field::integer('Limit', 'The most rows to return. Ahrefs charges per row.', 100, 1, 100000),
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'keyword,volume,difficulty,cpc,cps,clicks,traffic_potential,global_volume,parent_topic,intents,serp_features')),
                'where' => Field::advanced(Field::text('Filter', 'A filter in Ahrefs\' syntax, such as {"field":"domain_rating_source","is":["gte",50]}.', '{"field":"domain_rating_source","is":["gte",50]}')),
                'order_by' => Field::advanced(Field::text('Sort', 'Columns with optional direction, such as traffic:desc.', 'traffic:desc')),
                'keyword_list_id' => Field::dynamic('Keyword list', 'ahrefs.keyword_lists', 'Pick a keyword list, or enter its id.', '123456'),
                'keywords' => Field::text('Keywords', 'A comma-separated list of keywords to show metrics for.'),
                'target_position' => Field::advanced(Field::select('Target position', ['in_top10', 'in_top100'], 'Filters keywords based on the ranking position of the specified target.', null)),
                'mode' => Field::advanced(Field::select('Scope', ['exact', 'prefix', 'domain', 'subdomains'], 'The scope of the target.', null)),
                'volume_monthly_date_from' => Field::advanced(Field::text('Volume monthly date from', 'The start date in YYYY-MM-DD format for retrieving historical monthly search volumes in the volume_monthly_history field.')),
                'volume_monthly_date_to' => Field::advanced(Field::text('Volume monthly date to', 'The end date in YYYY-MM-DD format for retrieving historical monthly search volumes in the volume_monthly_history field.')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/keywords-explorer/overview',
            'items' => 'keywords',
            'select' => 'keyword,volume,difficulty,cpc,cps,clicks,traffic_potential,global_volume,parent_topic,intents,serp_features',
            'query' => ['target', 'country', 'limit', 'select', 'where', 'order_by', 'keyword_list_id', 'keywords', 'target_position', 'mode', 'volume_monthly_date_from', 'volume_monthly_date_to'],
            'limited' => true,
        ];
    }
}
