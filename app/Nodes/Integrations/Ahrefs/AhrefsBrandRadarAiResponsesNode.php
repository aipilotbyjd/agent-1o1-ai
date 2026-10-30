<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsBrandRadarAiResponsesNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_brand_radar_ai_responses';
    }

    public function name(): string
    {
        return 'Ahrefs: Brand Radar AI Responses';
    }

    public function description(): string
    {
        return 'AI chatbot responses that mention your brands.';
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
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'question,response,data_source,country,brand_sentiment,volume,links,last_updated')),
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
                'date_compared' => Field::advanced(Field::text('Compare with date', 'A date to compare date with, in YYYY-MM-DD format.')),
                'order_by' => Field::advanced(Field::json('Sort', 'How to order the results: a column, or how the responses talk about one brand, as the Sentiment page in Brand Radar can.')),
                'tags_filter' => Field::advanced(Field::json('Tags filter', 'A filter expression for prompt tags.')),
                'brand_filter' => Field::advanced(Field::json('Brand filter', 'A filter expression for your brand\'s visibility, mirroring the "Your brand" filter.')),
                'volume_range' => Field::advanced(Field::json('Volume range', 'The volume range to filter by. Such as {"from":1,"to":1}.')),
                'changes' => Field::advanced(Field::json('Changes', 'Requires date_compared. Such as {"prompt":[],"mentions":[],"citations":[]}.')),
                'sentiment_filter' => Field::advanced(Field::json('Sentiment filter', 'A filter on how the responses talk about your brands and competitors, like the Sentiment filter in Brand Radar.')),
                'opinions_first' => Field::advanced(Field::boolean('Opinions first', 'Puts the responses with a positive, negative or mixed mention of a tracked brand before the others, each group by relevance, as the Sentiment page in Brand Radar lists th', false)),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'POST',
            'path' => '/brand-radar/ai-responses',
            'items' => 'ai_responses',
            'select' => 'question,response,data_source,country,brand_sentiment,volume,links,last_updated',
            'body' => ['select', 'where', 'report_id', 'brands', 'competitors', 'content_filter', 'data_source', 'country', 'prompts', 'limit', 'date', 'date_compared', 'order_by', 'tags_filter', 'brand_filter', 'volume_range', 'changes', 'sentiment_filter', 'opinions_first'],
            'json' => ['where', 'brands', 'competitors', 'content_filter', 'order_by', 'tags_filter', 'brand_filter', 'volume_range', 'changes', 'sentiment_filter'],
            'lists' => ['select', 'data_source', 'country'],
            'integers' => ['limit'],
            'limited' => true,
        ];
    }
}
