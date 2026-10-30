<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsBrandRadarImpressionsOverviewNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_brand_radar_impressions_overview';
    }

    public function name(): string
    {
        return 'Ahrefs: Brand Radar Impressions Overview';
    }

    public function description(): string
    {
        return 'Brand impressions in AI chatbots.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['data_source'],
            'properties' => [
                ...$this->credentialFields(),
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'brand,entity_type,no_tracked_brands,only_competitors_brands,only_target_brand,target_and_competitors_brands,total')),
                'where' => Field::advanced(Field::json('Filter', 'The filter expression.')),
                'report_id' => Field::dynamic('Report', 'ahrefs.brand_radar_reports', 'Pick a Brand Radar report, or enter its id.', 'abc123'),
                'brands' => Field::advanced(Field::json('Brands', 'A list of brand names and websites to search for. Such as [{"names":["ahrefs","ahrefs seo"],"url_groups":[]}].', 'array')),
                'competitors' => Field::json('Competitors', 'A list of competitor names and websites to search for. Such as [{"names":["ahrefs","ahrefs seo"],"url_groups":[]}].', 'array'),
                'content_filter' => Field::advanced(Field::json('Content filter', 'Optional phrases used to include or exclude results based on their content. Such as {"include":[],"exclude":[]}.')),
                'data_source' => Field::list('Data source', 'A list of chatbot models.'),
                'country' => Field::list('Country', 'A list of two-letter country codes (ISO 3166-1 alpha-2).'),
                'prompts' => Field::select('Prompts', ['ahrefs', 'custom'], 'The type of prompts to use.', null),
                'tags_filter' => Field::advanced(Field::json('Tags filter', 'A filter expression for prompt tags.')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'POST',
            'path' => '/brand-radar/impressions-overview',
            'items' => 'metrics',
            'select' => 'brand,entity_type,no_tracked_brands,only_competitors_brands,only_target_brand,target_and_competitors_brands,total',
            'body' => ['select', 'where', 'report_id', 'brands', 'competitors', 'content_filter', 'data_source', 'country', 'prompts', 'tags_filter'],
            'json' => ['where', 'brands', 'competitors', 'content_filter', 'tags_filter'],
            'lists' => ['select', 'data_source', 'country'],
        ];
    }
}
