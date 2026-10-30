<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsUpdateBrandRadarReportNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_update_brand_radar_report';
    }

    public function name(): string
    {
        return 'Ahrefs: Update Brand Radar Report';
    }

    public function description(): string
    {
        return 'Changes how often a Brand Radar report runs its prompts.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['report_id', 'prompts_frequency'],
            'properties' => [
                ...$this->credentialFields(),
                'report_id' => Field::dynamic('Report', 'ahrefs.brand_radar_reports', 'Pick a Brand Radar report, or enter its id.', 'abc123'),
                'prompts_frequency' => Field::json('Prompts frequency', 'The prompts frequency to use. Such as [{"data_source":"chatgpt","frequency":"daily"}].', 'array'),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'PATCH',
            'path' => '/management/brand-radar-reports',
            'items' => 'reports',
            'body' => ['report_id', 'prompts_frequency'],
            'json' => ['prompts_frequency'],
        ];
    }
}
