<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushAudienceInsightsNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_audience_insights';
    }

    public function name(): string
    {
        return 'Semrush: Audience Insights';
    }

    public function description(): string
    {
        return 'How the audiences of websites overlap.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['targets', 'selected_targets'],
            'properties' => [
                ...$this->credentialFields(),
                'targets' => Field::list('Websites', 'Domains, subdomains or subfolders.', 'example.com'),
                'selected_targets' => Field::list('Selected websites', 'The ones to compare against the others.', 'example.com'),
                'segment' => Field::advanced(Field::select('Segment', ['contains', 'excludes', 'shares'])),
                'display_date' => Field::advanced(Field::text('Month', 'The first of the month, as YYYY-MM-01. Empty is last month.', '2026-01-01')),
                'device_type' => Field::advanced(Field::select('Device', ['desktop', 'mobile'])),
                'limit' => Field::integer('Limit', 'The most rows to return.', 100, 1, 5000),
                'offset' => Field::advanced(Field::integer('Skip', 'Rows to skip first.', null, 0)),
                'country' => Field::advanced(Field::text('Country', 'A two-letter code. Empty is worldwide.', 'US')),
                'columns' => Field::advanced(Field::text('Columns', 'Codes separated by commas. Empty returns the default ones.', 'target,visits')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->trendsReport($run, $config, 'audience_insights', ['targets', 'selected_targets', 'segment', 'display_date', 'device_type', 'country'], ['targets', 'selected_targets']);
    }
}
