<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsDeleteProjectsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_delete_projects';
    }

    public function name(): string
    {
        return 'Ahrefs: Delete Projects';
    }

    public function description(): string
    {
        return 'Deletes Rank Tracker projects.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'project_ids' => Field::text('Project ids', 'A comma-separated list of the IDs of the projects you want to delete.'),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'DELETE',
            'path' => '/management/projects',
            'items' => 'projects',
            'query' => ['project_ids'],
        ];
    }
}
