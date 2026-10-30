<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskSearchTriggersNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_search_triggers';
    }

    public function name(): string
    {
        return 'Zendesk: Search Business Rule Triggers';
    }

    public function description(): string
    {
        return 'Finds triggers by their title.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::text('Search', null, 'Notify'),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listing($run, $config, '/triggers/search', 'triggers', ['query' => $this->requiredString($config, 'query', 'The search')], null, 50);
    }
}
