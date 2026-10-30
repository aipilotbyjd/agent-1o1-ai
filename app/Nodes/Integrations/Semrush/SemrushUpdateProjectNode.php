<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushUpdateProjectNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_update_project';
    }

    public function name(): string
    {
        return 'Semrush: Rename Project';
    }

    public function description(): string
    {
        return 'Renames a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'project_name'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
                'project_name' => Field::text('New name', null, 'My site'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->semrushJson($run, $config, 'PUT', '/management/v1/projects', [], ['project_id' => (int) $this->projectId($config), 'project_name' => $this->requiredString($config, 'project_name', 'The new name')]);
    }
}
