<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushTopPagesNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_top_pages';
    }

    public function name(): string
    {
        return 'Semrush: Top Pages';
    }

    public function description(): string
    {
        return 'The most visited pages of a website.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['target'],
            'properties' => [
                ...$this->credentialFields(),
                'target' => Field::text('Website', 'A domain, subdomain or subfolder.', 'example.com'),
                'target_type' => Field::advanced(Field::select('Target type', ['domain', 'subdomain', 'subfolder'])),
                'display_date' => Field::advanced(Field::text('Month', 'The first of the month, as YYYY-MM-01. Empty is last month.', '2026-01-01')),
                'device_type' => Field::advanced(Field::select('Device', ['desktop', 'mobile'])),
                'limit' => Field::integer('Limit', 'The most rows to return.', 100, 1, 5000),
                'offset' => Field::advanced(Field::integer('Skip', 'Rows to skip first.', null, 0)),
                'country' => Field::advanced(Field::text('Country', 'A two-letter code. Empty is worldwide.', 'US')),
                'sort_order' => Field::advanced(Field::text('Sort', 'A column, with _asc or _desc added.', 'traffic_desc')),
                'columns' => Field::advanced(Field::text('Columns', 'Codes separated by commas. Empty returns the default ones.', 'target,visits')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->trendsReport($run, $config, 'toppages', ['target', 'target_type', 'display_date', 'device_type', 'country', 'sort_order']);
    }
}
