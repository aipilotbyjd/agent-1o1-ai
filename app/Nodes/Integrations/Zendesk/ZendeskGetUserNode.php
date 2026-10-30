<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskGetUserNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_get_user';
    }

    public function name(): string
    {
        return 'Zendesk: Get User';
    }

    public function description(): string
    {
        return 'Reads a person by id, or says they were not found.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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

        return $this->findOne($run, $config, "/users/{$id}", 'user', $id, $this->shapeUser(...));
    }
}
