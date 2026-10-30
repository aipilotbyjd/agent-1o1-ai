<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsCreateProjectsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_create_projects';
    }

    public function name(): string
    {
        return 'Ahrefs: Create Project';
    }

    public function description(): string
    {
        return 'Creates a Rank Tracker project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_name', 'mode', 'url', 'protocol'],
            'properties' => [
                ...$this->credentialFields(),
                'access' => Field::advanced(Field::select('Access', ['private', 'shared'], 'The access type of the project.', 'private')),
                'owned_by' => Field::advanced(Field::text('Owned by', 'The email of the project owner.')),
                'project_name' => Field::text('Project name', 'The name of the project.'),
                'mode' => Field::select('Scope', ['exact', 'prefix', 'domain', 'subdomains'], 'The scope of the target.', null),
                'url' => Field::text('Url', 'The URL of your target.'),
                'protocol' => Field::select('Protocol', ['both', 'http', 'https'], 'The protocol of your target.', null),
                'folder_id' => Field::advanced(Field::integer('Folder id', 'The folder id to assign to the project.')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'POST',
            'path' => '/management/projects',
            'items' => 'projects',
            'body' => ['access', 'owned_by', 'project_name', 'mode', 'url', 'protocol', 'folder_id'],
            'integers' => ['folder_id'],
        ];
    }
}
