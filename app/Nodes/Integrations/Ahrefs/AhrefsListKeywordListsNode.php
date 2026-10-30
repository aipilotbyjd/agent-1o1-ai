<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;

class AhrefsListKeywordListsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_list_keyword_lists';
    }

    public function name(): string
    {
        return 'Ahrefs: List Keyword Lists';
    }

    public function description(): string
    {
        return 'The saved keyword lists of the workspace.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/management/keyword-lists',
            'items' => 'keyword_lists',
        ];
    }
}
