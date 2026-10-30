<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsAddBrandRadarPromptsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_add_brand_radar_prompts';
    }

    public function name(): string
    {
        return 'Ahrefs: Add Brand Radar Prompts';
    }

    public function description(): string
    {
        return 'Adds custom prompts to a Brand Radar report.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['report_id', 'prompts', 'countries'],
            'properties' => [
                ...$this->credentialFields(),
                'report_id' => Field::dynamic('Report', 'ahrefs.brand_radar_reports', 'Pick a Brand Radar report, or enter its id.', 'abc123'),
                'prompts' => Field::list('Prompts', 'A list of custom prompts to add.'),
                'countries' => Field::list('Countries', 'A list of two-letter country codes (ISO 3166-1 alpha-2).'),
                'tags' => Field::advanced(Field::list('Tags', 'A list of tags to assign to the prompts.')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'POST',
            'path' => '/management/brand-radar-prompts',
            'items' => 'prompts',
            'query' => ['report_id'],
            'body' => ['prompts', 'countries', 'tags'],
            'lists' => ['prompts', 'countries', 'tags'],
        ];
    }
}
