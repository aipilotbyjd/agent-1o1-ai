<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskDeleteUserNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_delete_user';
    }

    public function name(): string
    {
        return 'Zendesk: Delete User';
    }

    public function description(): string
    {
        return 'Deletes a person. Their tickets stay.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['user_id'],
            'properties' => [
                ...$this->credentialFields(),
                'user_id' => Field::text('User id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'user_id', 'The user');
        $this->zendesk($run, $config, 'DELETE', "/users/{$id}");

        return ['deleted' => true, 'id' => (int) $id];
    }
}
