<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskGetDynamicContentNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_get_dynamic_content';
    }

    public function name(): string
    {
        return 'Zendesk: Get Dynamic Content Item';
    }

    public function description(): string
    {
        return 'Reads one dynamic content item and its translations.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['item_id'],
            'properties' => [
                ...$this->credentialFields(),
                'item_id' => Field::text('Item id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'item_id', 'The item');

        return $this->findOne($run, $config, "/dynamic_content/items/{$id}", 'item', $id);
    }
}
