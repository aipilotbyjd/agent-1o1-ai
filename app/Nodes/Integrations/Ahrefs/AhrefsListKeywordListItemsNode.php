<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsListKeywordListItemsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_list_keyword_list_items';
    }

    public function name(): string
    {
        return 'Ahrefs: List Keyword List Items';
    }

    public function description(): string
    {
        return 'The keywords of a keyword list.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['keyword_list_id'],
            'properties' => [
                ...$this->credentialFields(),
                'keyword_list_id' => Field::dynamic('Keyword list', 'ahrefs.keyword_lists', 'Pick a keyword list, or enter its id.', '123456'),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/management/keyword-list-keywords',
            'items' => 'keywords',
            'query' => ['keyword_list_id'],
        ];
    }
}
