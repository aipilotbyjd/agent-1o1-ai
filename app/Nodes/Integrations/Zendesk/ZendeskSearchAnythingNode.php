<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskSearchAnythingNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_search';
    }

    public function name(): string
    {
        return 'Zendesk: Search Anything';
    }

    public function description(): string
    {
        return 'Searches tickets, people, organizations and groups with Zendesk\'s search language.';
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
                'query' => Field::text('Search', null, 'type:user role:agent'),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listing($run, $config, '/search', 'results', ['query' => $this->requiredString($config, 'query', 'The search')]);
    }
}
