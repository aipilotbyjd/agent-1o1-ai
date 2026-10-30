<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushIndustryCategoriesNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_industry_categories';
    }

    public function name(): string
    {
        return 'Semrush: Industry Categories';
    }

    public function description(): string
    {
        return 'The top websites of an industry category.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['category'],
            'properties' => [
                ...$this->credentialFields(),
                'category' => Field::text('Category', 'An industry category name.', 'Arts_and_Entertainment'),
                'display_date' => Field::advanced(Field::text('Month', 'The first of the month, as YYYY-MM-01. Empty is last month.', '2026-01-01')),
                'country' => Field::advanced(Field::text('Country', 'A two-letter code. Empty is worldwide.', 'US')),
                'limit' => Field::integer('Limit', 'The most rows to return.', 100, 1, 5000),
                'offset' => Field::advanced(Field::integer('Skip', 'Rows to skip first.', null, 0)),
                'columns' => Field::advanced(Field::text('Columns', 'Codes separated by commas. Empty returns the default ones.', 'target,visits')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->trendsReport($run, $config, 'categories', ['category', 'display_date', 'country']);
    }
}
