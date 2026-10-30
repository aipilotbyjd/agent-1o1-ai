<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsUpdateBrandRadarPromptsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_update_brand_radar_prompts';
    }

    public function name(): string
    {
        return 'Ahrefs: Update Brand Radar Prompts';
    }

    public function description(): string
    {
        return 'Changes the tags of custom prompts of a Brand Radar report.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['report_id', 'prompts'],
            'properties' => [
                ...$this->credentialFields(),
                'report_id' => Field::dynamic('Report', 'ahrefs.brand_radar_reports', 'Pick a Brand Radar report, or enter its id.', 'abc123'),
                'prompts' => Field::list('Prompts', 'A list of custom prompts of the report to update.'),
                'countries' => Field::advanced(Field::json('Countries', 'The country operation to apply to the prompts.')),
                'tags' => Field::advanced(Field::json('Tags', 'The tag operation to apply to the prompts.')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'PATCH',
            'path' => '/management/brand-radar-prompts',
            'items' => 'prompts',
            'query' => ['report_id'],
            'body' => ['prompts', 'countries', 'tags'],
            'json' => ['countries', 'tags'],
            'lists' => ['prompts'],
        ];
    }
}
