<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsDeleteBrandRadarPromptsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_delete_brand_radar_prompts';
    }

    public function name(): string
    {
        return 'Ahrefs: Delete Brand Radar Prompts';
    }

    public function description(): string
    {
        return 'Removes custom prompts from a Brand Radar report.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['report_id', 'prompts'],
            'properties' => [
                ...$this->credentialFields(),
                'report_id' => Field::dynamic('Report', 'ahrefs.brand_radar_reports', 'Pick a Brand Radar report, or enter its id.', 'abc123'),
                'prompts' => Field::list('Prompts', 'A list of custom prompts to delete.'),
                'countries' => Field::advanced(Field::list('Countries', 'A list of two-letter country codes (ISO 3166-1 alpha-2).')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'PUT',
            'path' => '/management/brand-radar-prompts-delete',
            'items' => 'prompts',
            'query' => ['report_id'],
            'body' => ['prompts', 'countries'],
            'lists' => ['prompts', 'countries'],
        ];
    }
}
