<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushCreateProjectNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_create_project';
    }

    public function name(): string
    {
        return 'Semrush: Create Project';
    }

    public function description(): string
    {
        return 'Creates a project for a domain.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['url'],
            'properties' => [
                ...$this->credentialFields(),
                'url' => Field::text('Domain', null, 'example.com'),
                'project_name' => Field::text('Name', 'It can\'t hold symbols such as ~ ! # % & * = [ ] / { } | : < > ?', 'My site'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->semrushJson($run, $config, 'POST', '/management/v1/projects', [], array_filter(['url' => $this->domain($config, 'url'), 'project_name' => filled($config['project_name'] ?? null) ? (string) $config['project_name'] : null]));
    }
}
