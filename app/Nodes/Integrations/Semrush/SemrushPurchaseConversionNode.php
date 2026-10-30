<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushPurchaseConversionNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_purchase_conversion';
    }

    public function name(): string
    {
        return 'Semrush: Purchase Conversion';
    }

    public function description(): string
    {
        return 'The share of desktop sessions ending in a purchase.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['target', 'device_type'],
            'properties' => [
                ...$this->credentialFields(),
                'target' => Field::text('Website', 'A domain, subdomain or subfolder.', 'example.com'),
                'device_type' => Field::select('Device', ['desktop'], 'Only desktop has this data.', 'desktop'),
                'display_date' => Field::advanced(Field::text('Month', 'The first of the month, as YYYY-MM-01. Empty is last month.', '2026-01-01')),
                'country' => Field::advanced(Field::text('Country', 'A two-letter code. Empty is worldwide.', 'US')),
                'columns' => Field::advanced(Field::text('Columns', 'Codes separated by commas. Empty returns the default ones.', 'target,visits')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->trendsReport($run, $config, 'purchase_conversion', ['target', 'device_type', 'display_date', 'country']);
    }
}
