<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SemrushGetProjectNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_get_project';
    }

    public function name(): string
    {
        return 'Semrush: Get Project';
    }

    public function description(): string
    {
        return 'Reads a project: its name, domain and tools.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        return $this->semrushJson($run, $config, 'GET', '/management/v1/projects/'.$this->projectId($config));
    }
}
