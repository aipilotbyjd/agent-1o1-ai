<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsDeleteProjectCompetitorsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_delete_project_competitors';
    }

    public function name(): string
    {
        return 'Ahrefs: Delete Project Competitors';
    }

    public function description(): string
    {
        return 'Removes competitors from a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'competitors'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
                'competitors' => Field::json('Competitors', 'A list of competitors to delete. Such as [{"url":"example.com","mode":"exact"}].', 'array'),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'POST',
            'path' => '/management/project-competitors-delete',
            'items' => 'competitors',
            'query' => ['project_id'],
            'body' => ['competitors'],
            'json' => ['competitors'],
        ];
    }
}
