<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushTrafficSummaryNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_traffic_summary';
    }

    public function name(): string
    {
        return 'Semrush: Traffic Summary';
    }

    public function description(): string
    {
        return 'Visits, visitors, pages per visit and bounce rate of up to 200 websites.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['targets'],
            'properties' => [
                ...$this->credentialFields(),
                'targets' => Field::list('Websites', 'Domains, subdomains or subfolders.', 'example.com'),
                'display_date' => Field::advanced(Field::text('Month', 'The first of the month, as YYYY-MM-01. Empty is last month.', '2026-01-01')),
                'device_type' => Field::advanced(Field::select('Device', ['desktop', 'mobile'])),
                'country' => Field::advanced(Field::text('Country', 'A two-letter code. Empty is worldwide.', 'US')),
                'columns' => Field::advanced(Field::text('Columns', 'Codes separated by commas. Empty returns the default ones.', 'target,visits')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->trendsReport($run, $config, 'summary', ['targets', 'display_date', 'device_type', 'country'], ['targets']);
    }
}
