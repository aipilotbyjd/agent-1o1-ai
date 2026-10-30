<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetRoleNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_role';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Role';
    }

    public function description(): string
    {
        return 'Reads a role by id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['role_id'],
            'properties' => [
                ...$this->credentialFields(),
                'role_id' => Field::text('Role id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'role_id', 'The role');

        return $this->findOne($run, $config, "/roles/{$id}", $id);
    }
}
