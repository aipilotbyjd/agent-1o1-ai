<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsBrandRadarSovHistoryNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_brand_radar_sov_history';
    }

    public function name(): string
    {
        return 'Ahrefs: Brand Radar Share of Voice History';
    }

    public function description(): string
    {
        return 'Share of voice among brands in AI chatbots over time.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['data_source', 'date_from'],
            'properties' => [
                ...$this->credentialFields(),
                'where' => Field::advanced(Field::json('Filter', 'The filter expression.')),
                'report_id' => Field::dynamic('Report', 'ahrefs.brand_radar_reports', 'Pick a Brand Radar report, or enter its id.', 'abc123'),
                'brands' => Field::advanced(Field::json('Brands', 'A list of brand names and websites to search for. Such as [{"names":["ahrefs","ahrefs seo"],"url_groups":[]}].', 'array')),
                'competitors' => Field::json('Competitors', 'A list of competitor names and websites to search for. Such as [{"names":["ahrefs","ahrefs seo"],"url_groups":[]}].', 'array'),
                'content_filter' => Field::advanced(Field::json('Content filter', 'Optional phrases used to include or exclude results based on their content. Such as {"include":[],"exclude":[]}.')),
                'data_source' => Field::list('Data source', 'A list of chatbot models.'),
                'country' => Field::list('Country', 'A list of two-letter country codes (ISO 3166-1 alpha-2).'),
                'prompts' => Field::select('Prompts', ['ahrefs', 'custom'], 'The type of prompts to use.', null),
                'date_from' => Field::text('From', 'The start date of the historical period in YYYY-MM-DD format.'),
                'date_to' => Field::text('To', 'The end date of the historical period in YYYY-MM-DD format.'),
                'tags_filter' => Field::advanced(Field::json('Tags filter', 'A filter expression for prompt tags.')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'POST',
            'path' => '/brand-radar/sov-history',
            'items' => 'metrics',
            'body' => ['where', 'report_id', 'brands', 'competitors', 'content_filter', 'data_source', 'country', 'prompts', 'date_from', 'date_to', 'tags_filter'],
            'json' => ['where', 'brands', 'competitors', 'content_filter', 'tags_filter'],
            'lists' => ['data_source', 'country'],
        ];
    }
}
