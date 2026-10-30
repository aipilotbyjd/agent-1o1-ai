<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushWeeklyTrafficNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_weekly_traffic';
    }

    public function name(): string
    {
        return 'Semrush: Weekly Traffic';
    }

    public function description(): string
    {
        return 'Week by week visits, traffic sources and engagement of a website.';
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
                'display_date' => Field::advanced(Field::text('Month', 'The first of the month, as YYYY-MM-01. Empty is last month.', '2026-01-01')),
                'target_type' => Field::advanced(Field::select('Target type', ['domain', 'subdomain', 'subfolder'])),
                'device_type' => Field::advanced(Field::select('Device', ['desktop', 'mobile'])),
                'country' => Field::advanced(Field::text('Country', 'A two-letter code. Empty is worldwide.', 'US')),
                'include_forecasted_items' => Field::advanced(Field::boolean('Include forecasted days', null, false)),
                'columns' => Field::advanced(Field::text('Columns', 'Codes separated by commas. Empty returns the default ones.', 'target,visits')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->trendsReport($run, $config, 'summary_by_week', ['target', 'display_date', 'target_type', 'device_type', 'country', 'include_forecasted_items']);
    }
}
