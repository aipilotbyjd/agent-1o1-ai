<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsAddProjectKeywordTagsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_add_project_keyword_tags';
    }

    public function name(): string
    {
        return 'Ahrefs: Add Project Keyword Tags';
    }

    public function description(): string
    {
        return 'Adds tags to a project\'s keywords.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'keywords', 'tags'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
                'keywords' => Field::json('Keywords', 'A list of keywords to add. Such as [{"keyword":"example.com"}].', 'array'),
                'tags' => Field::list('Tags', 'A list of tags to add to the keywords.'),
                'update_mode' => Field::advanced(Field::select('Update mode', ['add', 'replace'], 'add appends tags to existing ones; replace overwrites all existing tags entirely.', 'add')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'PUT',
            'path' => '/management/project-keywords-tags',
            'items' => 'keywords',
            'body' => ['project_id', 'keywords', 'tags', 'update_mode'],
            'json' => ['keywords'],
            'lists' => ['tags'],
            'integers' => ['project_id'],
        ];
    }
}
