<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskFindDynamicContentNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_find_dynamic_content';
    }

    public function name(): string
    {
        return 'Zendesk: Find Dynamic Content By Name';
    }

    public function description(): string
    {
        return 'Finds dynamic content items by name.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name contains', null, 'Greeting'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $name = mb_strtolower($this->requiredString($config, 'name', 'The name'));
        $items = array_values(array_filter($this->listAll($run, $config, '/dynamic_content/items', 'items', [], 1000), fn (array $item): bool => str_contains(mb_strtolower((string) ($item['name'] ?? '')), $name)));

        return ['items' => $items, 'count' => count($items)];
    }
}
