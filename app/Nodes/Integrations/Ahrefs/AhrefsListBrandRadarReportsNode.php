<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;

class AhrefsListBrandRadarReportsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_list_brand_radar_reports';
    }

    public function name(): string
    {
        return 'Ahrefs: List Brand Radar Reports';
    }

    public function description(): string
    {
        return 'The Brand Radar reports of the workspace.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/management/brand-radar-reports',
            'items' => 'reports',
        ];
    }
}
