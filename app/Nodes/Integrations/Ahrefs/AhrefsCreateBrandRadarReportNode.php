<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsCreateBrandRadarReportNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_create_brand_radar_report';
    }

    public function name(): string
    {
        return 'Ahrefs: Create Brand Radar Report';
    }

    public function description(): string
    {
        return 'Creates a Brand Radar report.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'brand' => Field::advanced(Field::json('Brand', 'At least one of brand or competitors is required to be non-empty.')),
                'competitors' => Field::json('Competitors', 'At least one of brand or competitors is required to be non-empty.'),
                'name' => Field::text('Name', 'The name of the report.'),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
                'content_filter' => Field::advanced(Field::json('Content filter', 'Optional phrases used to include or exclude results based on their content. Such as {"include":[],"exclude":[]}.')),
                'prompts_frequency' => Field::advanced(Field::json('Prompts frequency', 'The prompts frequency to use. Such as [{"data_source":"chatgpt","frequency":"daily"}].', 'array')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'POST',
            'path' => '/management/brand-radar-reports',
            'items' => 'reports',
            'body' => ['brand', 'competitors', 'name', 'project_id', 'content_filter', 'prompts_frequency'],
            'json' => ['brand', 'competitors', 'content_filter', 'prompts_frequency'],
            'integers' => ['project_id'],
        ];
    }
}
