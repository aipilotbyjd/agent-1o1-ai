<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsContentGapNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_content_gap';
    }

    public function name(): string
    {
        return 'Ahrefs: Content Gap';
    }

    public function description(): string
    {
        return 'The keywords your competitors rank for and you don\'t.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['target', 'competitors', 'country'],
            'properties' => [
                ...$this->credentialFields(),
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'keyword,volume,keyword_difficulty,cpc,competitors_count')),
                'where' => Field::advanced(Field::json('Filter', 'The filter expression.')),
                'order_by' => Field::advanced(Field::list('Sort', 'Columns to order results by, with optional :asc or :desc direction.')),
                'limit' => Field::integer('Limit', 'The most rows to return. Ahrefs charges per row.', 100, 1, 100000),
                'target' => Field::json('Target', 'The target whose keyword gaps are analyzed. Such as {"url":"example.com","mode":"exact","protocol":"both"}.'),
                'competitors' => Field::json('Competitors', 'The competitors whose rankings are compared with the target, in output column order. Such as [{"url":"example.com","mode":"exact","protocol":"both"}].', 'array'),
                'country' => Field::text('Country', 'The country whose search results are analyzed.'),
                'date' => Field::text('Date', 'A date to report metrics on in YYYY-MM-DD format.'),
                'volume_mode' => Field::advanced(Field::select('Volume mode', ['monthly', 'average'], 'The search volume calculation mode.', 'monthly')),
                'competitor_position_from' => Field::advanced(Field::integer('Competitor position from', 'The inclusive lower ranking bound used by competitors_count_in_range.')),
                'competitor_position_to' => Field::advanced(Field::integer('Competitor position to', 'The inclusive upper ranking bound used by competitors_count_in_range.')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'POST',
            'path' => '/competitive-analysis/content-gap',
            'items' => 'keywords',
            'select' => 'keyword,volume,keyword_difficulty,cpc,competitors_count',
            'body' => ['select', 'where', 'order_by', 'limit', 'target', 'competitors', 'country', 'date', 'volume_mode', 'competitor_position_from', 'competitor_position_to'],
            'json' => ['where', 'target', 'competitors'],
            'lists' => ['select', 'order_by'],
            'integers' => ['limit', 'competitor_position_from', 'competitor_position_to'],
            'dated' => true,
            'limited' => true,
        ];
    }
}
