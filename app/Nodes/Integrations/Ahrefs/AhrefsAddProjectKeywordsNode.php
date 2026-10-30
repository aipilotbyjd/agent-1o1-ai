<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsAddProjectKeywordsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_add_project_keywords';
    }

    public function name(): string
    {
        return 'Ahrefs: Add Project Keywords';
    }

    public function description(): string
    {
        return 'Adds keywords to a project in chosen locations.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'keywords', 'locations'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
                'keywords' => Field::json('Keywords', 'A list of keywords to add. Such as [{"keyword":"example.com"}].', 'array'),
                'locations' => Field::json('Locations', 'A list of locations to assign to given keywords. Such as [{"country":"ad"}].', 'array'),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'PUT',
            'path' => '/management/project-keywords',
            'items' => 'keywords',
            'query' => ['project_id'],
            'body' => ['keywords', 'locations'],
            'json' => ['keywords', 'locations'],
        ];
    }
}
