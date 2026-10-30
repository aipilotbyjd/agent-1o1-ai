<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsBrandRadarCitedPagesNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_brand_radar_cited_pages';
    }

    public function name(): string
    {
        return 'Ahrefs: Brand Radar Cited Pages';
    }

    public function description(): string
    {
        return 'The pages cited in AI chatbot responses about your brands.';
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
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'mentions,responses,title,url,volume')),
                'where' => Field::advanced(Field::json('Filter', 'The filter expression.')),
                'report_id' => Field::dynamic('Report', 'ahrefs.brand_radar_reports', 'Pick a Brand Radar report, or enter its id.', 'abc123'),
                'brands' => Field::advanced(Field::json('Brands', 'A list of brand names and websites to search for. Such as [{"names":["ahrefs","ahrefs seo"],"url_groups":[]}].', 'array')),
                'competitors' => Field::json('Competitors', 'A list of competitor names and websites to search for. Such as [{"names":["ahrefs","ahrefs seo"],"url_groups":[]}].', 'array'),
                'content_filter' => Field::advanced(Field::json('Content filter', 'Optional phrases used to include or exclude results based on their content. Such as {"include":[],"exclude":[]}.')),
                'data_source' => Field::list('Data source', 'A list of chatbot models.'),
                'country' => Field::list('Country', 'A list of two-letter country codes (ISO 3166-1 alpha-2).'),
                'prompts' => Field::select('Prompts', ['ahrefs', 'custom'], 'The type of prompts to use.', null),
                'limit' => Field::integer('Limit', 'The most rows to return. Ahrefs charges per row.', 100, 1, 100000),
                'date' => Field::text('Date', 'The date to search for in YYYY-MM-DD format.'),
                'tracked_urls' => Field::advanced(Field::list('Tracked urls', 'A list of tracked page URLs.')),
                'tags_filter' => Field::advanced(Field::json('Tags filter', 'A filter expression for prompt tags.')),
                'brand_filter' => Field::advanced(Field::json('Brand filter', 'A filter expression for your brand\'s visibility, mirroring the "Your brand" filter.')),
                'volume_range' => Field::advanced(Field::json('Volume range', 'The volume range to filter by. Such as {"from":1,"to":1}.')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'POST',
            'path' => '/brand-radar/cited-pages',
            'items' => 'pages',
            'select' => 'mentions,responses,title,url,volume',
            'body' => ['select', 'where', 'report_id', 'brands', 'competitors', 'content_filter', 'data_source', 'country', 'prompts', 'limit', 'date', 'tracked_urls', 'tags_filter', 'brand_filter', 'volume_range'],
            'json' => ['where', 'brands', 'competitors', 'content_filter', 'tags_filter', 'brand_filter', 'volume_range'],
            'lists' => ['select', 'data_source', 'country', 'tracked_urls'],
            'integers' => ['limit'],
            'limited' => true,
        ];
    }
}
