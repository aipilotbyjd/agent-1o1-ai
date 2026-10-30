<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskSearchUsersNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_search_users';
    }

    public function name(): string
    {
        return 'Zendesk: Search Users';
    }

    public function description(): string
    {
        return 'Finds people by name, email, role, tag and more with Zendesk\'s search language.';
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
                'query' => Field::text('Search', 'An email, a name, or terms such as role:agent or tags:vip.', 'ada@example.com'),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listing($run, $config, '/users/search', 'users', ['query' => $this->requiredString($config, 'query', 'The search')], $this->shapeUser(...), 50);
    }
}
