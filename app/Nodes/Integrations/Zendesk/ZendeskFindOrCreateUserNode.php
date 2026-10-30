<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskFindOrCreateUserNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_find_or_create_user';
    }

    public function name(): string
    {
        return 'Zendesk: Find or Create User';
    }

    public function description(): string
    {
        return 'Finds the person with this email, or creates them.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email'],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'name' => Field::text('Name', 'Used when they are new.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $email = $this->email($config);
        $rows = (array) ($this->zendesk($run, $config, 'GET', '/users/search', ['query' => "email:{$email}"])['users'] ?? []);
        if ($rows !== []) {
            return ['created' => false, ...$this->shapeUser((array) $rows[0])];
        }
        $user = (array) ($this->zendesk($run, $config, 'POST', '/users', [], ['user' => ['email' => $email, 'name' => $this->text($config, 'name') ?? strstr($email, '@', true)]])['user'] ?? []);

        return ['created' => true, ...$this->shapeUser($user)];
    }
}
