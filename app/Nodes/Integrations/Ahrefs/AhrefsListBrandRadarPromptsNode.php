<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsListBrandRadarPromptsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_list_brand_radar_prompts';
    }

    public function name(): string
    {
        return 'Ahrefs: List Brand Radar Prompts';
    }

    public function description(): string
    {
        return 'The custom prompts of a Brand Radar report.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['report_id'],
            'properties' => [
                ...$this->credentialFields(),
                'report_id' => Field::dynamic('Report', 'ahrefs.brand_radar_reports', 'Pick a Brand Radar report, or enter its id.', 'abc123'),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/management/brand-radar-prompts',
            'items' => 'prompts',
            'query' => ['report_id'],
        ];
    }
}
