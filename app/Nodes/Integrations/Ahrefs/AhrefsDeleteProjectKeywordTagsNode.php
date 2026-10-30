<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsDeleteProjectKeywordTagsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_delete_project_keyword_tags';
    }

    public function name(): string
    {
        return 'Ahrefs: Delete Project Keyword Tags';
    }

    public function description(): string
    {
        return 'Removes tags from a project\'s keywords.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'keywords', 'tags'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
                'keywords' => Field::json('Keywords', 'A list of keywords to delete. Such as [{"keyword":"example.com"}].', 'array'),
                'tags' => Field::list('Tags', 'A list of tags to remove from the keywords.'),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'PUT',
            'path' => '/management/project-keywords-tags-delete',
            'items' => 'keywords',
            'body' => ['project_id', 'keywords', 'tags'],
            'json' => ['keywords'],
            'lists' => ['tags'],
            'integers' => ['project_id'],
        ];
    }
}
