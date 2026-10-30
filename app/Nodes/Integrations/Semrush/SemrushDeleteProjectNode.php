<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SemrushDeleteProjectNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_delete_project';
    }

    public function name(): string
    {
        return 'Semrush: Delete Project';
    }

    public function description(): string
    {
        return 'Deletes a project and every campaign in it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->semrushJson($run, $config, 'DELETE', '/management/v1/projects/'.$this->projectId($config));

        return ['deleted' => true];
    }
}
