<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskSearchAutomationsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_search_automations';
    }

    public function name(): string
    {
        return 'Zendesk: Search Automations';
    }

    public function description(): string
    {
        return 'Finds automations by their title.';
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
                'query' => Field::text('Search', null, 'Close'),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listing($run, $config, '/automations/search', 'automations', ['query' => $this->requiredString($config, 'query', 'The search')], null, 50);
    }
}
