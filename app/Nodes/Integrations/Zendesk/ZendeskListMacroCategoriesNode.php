<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ZendeskListMacroCategoriesNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_list_macro_categories';
    }

    public function name(): string
    {
        return 'Zendesk: List Macro Categories';
    }

    public function description(): string
    {
        return 'Lists the categories macros are sorted into.';
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

    public function execute(Run $run, array $config, array $context): array
    {
        $categories = (array) ($this->zendesk($run, $config, 'GET', '/macros/categories')['categories'] ?? []);

        return ['categories' => $categories, 'count' => count($categories)];
    }
}
