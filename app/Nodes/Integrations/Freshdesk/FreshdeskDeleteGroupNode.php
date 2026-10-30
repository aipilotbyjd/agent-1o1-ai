<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskDeleteGroupNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_delete_group';
    }

    public function name(): string
    {
        return 'Freshdesk: Delete Group';
    }

    public function description(): string
    {
        return 'Deletes an agent group.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['group_id'],
            'properties' => [
                ...$this->credentialFields(),
                'group_id' => Field::dynamic('Group', 'freshdesk.groups'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'group_id', 'The group');

        return $this->destroy($run, $config, "/groups/{$id}", $id);
    }
}
