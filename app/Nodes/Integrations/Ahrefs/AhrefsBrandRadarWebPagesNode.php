<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsBrandRadarWebPagesNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_brand_radar_web_pages';
    }

    public function name(): string
    {
        return 'Ahrefs: Brand Radar Web Pages';
    }

    public function description(): string
    {
        return 'Web pages about your brands.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'url,title,domain_rating,referring_domains,traffic,traffic_value')),
                'where' => Field::advanced(Field::json('Filter', 'The filter expression.')),
                'report_id' => Field::dynamic('Report', 'ahrefs.brand_radar_reports', 'Pick a Brand Radar report, or enter its id.', 'abc123'),
                'brands' => Field::advanced(Field::json('Brands', 'A list of brand names and websites to find mentions of on web pages. Such as [{"names":["ahrefs","ahrefs seo"],"url_groups":[]}].', 'array')),
                'competitors' => Field::json('Competitors', 'A list of competitor names and websites to find mentions of on web pages. Such as [{"names":["ahrefs","ahrefs seo"],"url_groups":[]}].', 'array'),
                'content_filter' => Field::advanced(Field::json('Content filter', 'Optional phrases used to include or exclude results based on their content. Such as {"include":[],"exclude":[]}.')),
                'limit' => Field::integer('Limit', 'The most rows to return. Ahrefs charges per row.', 100, 1, 100000),
                'order_by' => Field::advanced(Field::text('Sort', 'Columns with optional direction, such as traffic:desc.', 'traffic:desc')),
                'page_type' => Field::advanced(Field::select('Page type', ['all_pages', 'news'], 'Whether to return all web pages or only news pages.', 'all_pages')),
                'publication_type' => Field::advanced(Field::select('Publication type', ['all', 'published_once', 'republished'], 'Whether to return all pages, pages published once, or republished pages.', 'all')),
                'date_from' => Field::text('From', 'The start of the publication-date range, in YYYY-MM-DD format.'),
                'date_to' => Field::text('To', 'The inclusive end of the publication-date range, in YYYY-MM-DD format.'),
                'platform' => Field::advanced(Field::select('Platform', ['all', 'blogs', 'wordpress', 'ecommerce', 'cms', 'message-boards', 'wikis'], 'The platform or technology used by the page.', 'all')),
                'language' => Field::advanced(Field::text('Language', 'The language of the page.')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'POST',
            'path' => '/brand-radar/web-pages',
            'items' => 'pages',
            'select' => 'url,title,domain_rating,referring_domains,traffic,traffic_value',
            'body' => ['select', 'where', 'report_id', 'brands', 'competitors', 'content_filter', 'limit', 'order_by', 'page_type', 'publication_type', 'date_from', 'date_to', 'platform', 'language'],
            'json' => ['where', 'brands', 'competitors', 'content_filter'],
            'lists' => ['select'],
            'integers' => ['limit'],
            'limited' => true,
        ];
    }
}
