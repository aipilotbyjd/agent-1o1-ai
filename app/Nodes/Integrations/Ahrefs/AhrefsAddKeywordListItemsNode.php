<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsAddKeywordListItemsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_add_keyword_list_items';
    }

    public function name(): string
    {
        return 'Ahrefs: Add to Keyword List';
    }

    public function description(): string
    {
        return 'Adds keywords to a keyword list.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['keyword_list_id', 'keywords'],
            'properties' => [
                ...$this->credentialFields(),
                'keyword_list_id' => Field::dynamic('Keyword list', 'ahrefs.keyword_lists', 'Pick a keyword list, or enter its id.', '123456'),
                'keywords' => Field::list('Keywords', 'A list of keywords to add.'),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'PUT',
            'path' => '/management/keyword-list-keywords',
            'items' => 'keywords',
            'query' => ['keyword_list_id'],
            'body' => ['keywords'],
            'lists' => ['keywords'],
        ];
    }
}
