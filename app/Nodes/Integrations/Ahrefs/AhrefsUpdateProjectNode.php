<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsUpdateProjectNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_update_project';
    }

    public function name(): string
    {
        return 'Ahrefs: Update Project';
    }

    public function description(): string
    {
        return 'Moves a project to a folder or changes who can see it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
                'folder' => Field::advanced(Field::json('Folder', 'A folder operation, applied before the access change.')),
                'access' => Field::advanced(Field::select('Access', ['private', 'shared'], 'The new access setting for the project.', null)),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'PATCH',
            'path' => '/management/update-project',
            'items' => 'project',
            'body' => ['project_id', 'folder', 'access'],
            'json' => ['folder'],
            'integers' => ['project_id'],
        ];
    }
}
