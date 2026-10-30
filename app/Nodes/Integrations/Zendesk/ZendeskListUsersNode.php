<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskListUsersNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_list_users';
    }

    public function name(): string
    {
        return 'Zendesk: List Users';
    }

    public function description(): string
    {
        return 'Lists people, optionally of one role.';
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
                'role' => Field::advanced(Field::select('Role', ['end-user' => 'End user', 'agent' => 'Agent', 'admin' => 'Admin'])),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listing($run, $config, '/users', 'users', ['role' => $this->text($config, 'role')], $this->shapeUser(...));
    }
}
