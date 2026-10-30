<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsListProjectsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_list_projects';
    }

    public function name(): string
    {
        return 'Ahrefs: List Projects';
    }

    public function description(): string
    {
        return 'The Rank Tracker projects of the workspace.';
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
                'access' => Field::advanced(Field::select('Access', ['private', 'shared'], 'The access type of the project.', null)),
                'owned_by' => Field::advanced(Field::text('Owned by', 'The email of the project owner')),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
                'has_keywords' => Field::advanced(Field::boolean('Has keywords', 'Has Rank Tracker keywords.', false)),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/management/projects',
            'items' => 'projects',
            'query' => ['access', 'owned_by', 'project_id', 'has_keywords'],
        ];
    }
}
