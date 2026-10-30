<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushOccupationDistributionNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_occupation_distribution';
    }

    public function name(): string
    {
        return 'Semrush: Occupation Distribution';
    }

    public function description(): string
    {
        return 'A website\'s audience by occupation.';
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
                'device_type' => Field::advanced(Field::select('Device', ['desktop', 'mobile'])),
                'country' => Field::advanced(Field::text('Country', 'A two-letter code. Empty is worldwide.', 'US')),
                'columns' => Field::advanced(Field::text('Columns', 'Codes separated by commas. Empty returns the default ones.', 'target,visits')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->trendsReport($run, $config, 'occupation_distribution', ['target', 'display_date', 'device_type', 'country']);
    }
}
