<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsDeleteProjectKeywordsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_delete_project_keywords';
    }

    public function name(): string
    {
        return 'Ahrefs: Delete Project Keywords';
    }

    public function description(): string
    {
        return 'Stops tracking keywords in a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'keywords'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
                'keywords' => Field::json('Keywords', 'A list of keywords to delete. Such as [{"keyword":"example.com"}].', 'array'),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'PUT',
            'path' => '/management/project-keywords-delete',
            'items' => 'keywords',
            'query' => ['project_id'],
            'body' => ['keywords'],
            'json' => ['keywords'],
        ];
    }
}
