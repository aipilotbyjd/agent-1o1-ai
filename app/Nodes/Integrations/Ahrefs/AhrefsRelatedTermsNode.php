<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsRelatedTermsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_related_terms';
    }

    public function name(): string
    {
        return 'Ahrefs: Related Terms';
    }

    public function description(): string
    {
        return 'Keyword ideas related to your terms.';
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
                'country' => Field::text('Country', 'A two-letter country code (ISO 3166-1 alpha-2).'),
                'limit' => Field::integer('Limit', 'The most rows to return. Ahrefs charges per row.', 100, 1, 100000),
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'keyword,volume,difficulty,cpc,cps,traffic_potential,global_volume,parent_topic,intents,serp_features')),
                'where' => Field::advanced(Field::text('Filter', 'A filter in Ahrefs\' syntax, such as {"field":"domain_rating_source","is":["gte",50]}.', '{"field":"domain_rating_source","is":["gte",50]}')),
                'order_by' => Field::advanced(Field::text('Sort', 'Columns with optional direction, such as traffic:desc.', 'traffic:desc')),
                'keyword_list_id' => Field::dynamic('Keyword list', 'ahrefs.keyword_lists', 'Pick a keyword list, or enter its id.', '123456'),
                'keywords' => Field::text('Keywords', 'A comma-separated list of keywords to show metrics for.'),
                'terms' => Field::select('Terms', ['all', 'also_rank_for', 'also_talk_about'], 'Related keywords which top-ranking pages also rank for (also_rank_for), additional keywords frequently mentioned in top-ranking pages (also_talk_about), or combination of', 'all'),
                'view_for' => Field::advanced(Field::select('View for', ['top_10', 'top_100'], 'View keywords for the top 10 or top 100 ranking pages.', 'top_10')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/keywords-explorer/related-terms',
            'items' => 'keywords',
            'select' => 'keyword,volume,difficulty,cpc,cps,traffic_potential,global_volume,parent_topic,intents,serp_features',
            'query' => ['country', 'limit', 'select', 'where', 'order_by', 'keyword_list_id', 'keywords', 'terms', 'view_for'],
            'limited' => true,
        ];
    }
}
