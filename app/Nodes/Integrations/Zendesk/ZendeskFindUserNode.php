<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskFindUserNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_find_user';
    }

    public function name(): string
    {
        return 'Zendesk: Find User';
    }

    public function description(): string
    {
        return 'Finds one person by email, or says there is none.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email'],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email', null, 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = (array) ($this->zendesk($run, $config, 'GET', '/users/search', ['query' => 'email:'.$this->email($config)])['users'] ?? []);

        return $rows === [] ? ['found' => false] : ['found' => true, ...$this->shapeUser((array) $rows[0])];
    }
}
