<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsDeleteKeywordListItemsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_delete_keyword_list_items';
    }

    public function name(): string
    {
        return 'Ahrefs: Delete from Keyword List';
    }

    public function description(): string
    {
        return 'Removes keywords from a keyword list.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['keyword_list_id', 'keywords'],
            'properties' => [
                ...$this->credentialFields(),
                'keyword_list_id' => Field::dynamic('Keyword list', 'ahrefs.keyword_lists', 'Pick a keyword list, or enter its id.', '123456'),
                'keywords' => Field::list('Keywords', 'A list of keywords to delete.'),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'PUT',
            'path' => '/management/keyword-list-keywords-delete',
            'items' => 'keywords',
            'query' => ['keyword_list_id'],
            'body' => ['keywords'],
            'lists' => ['keywords'],
        ];
    }
}
