<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsDeleteKeywordListsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_delete_keyword_lists';
    }

    public function name(): string
    {
        return 'Ahrefs: Delete Keyword Lists';
    }

    public function description(): string
    {
        return 'Deletes keyword lists.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['keyword_list_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'keyword_list_ids' => Field::text('Keyword list ids', 'A comma-separated list of the IDs of the keyword lists to delete.'),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'DELETE',
            'path' => '/management/keyword-lists',
            'items' => 'keyword_lists',
            'query' => ['keyword_list_ids'],
        ];
    }
}
