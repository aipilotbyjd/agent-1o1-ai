<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushListProjectsNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_list_projects';
    }

    public function name(): string
    {
        return 'Semrush: List Projects';
    }

    public function description(): string
    {
        return 'Lists your projects and the tools switched on in each.';
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
                'filter' => Field::advanced(Field::select('Which', ['all', 'own', 'shared', 'corporate'], null, 'own')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $projects = array_values($this->semrushJson($run, $config, 'GET', '/management/v1/projects', ['filter' => $config['filter'] ?? null]));

        return ['projects' => $projects, 'count' => count($projects)];
    }
}
