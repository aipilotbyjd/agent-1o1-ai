<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskDeleteGroupNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_delete_group';
    }

    public function name(): string
    {
        return 'Zendesk: Delete Group';
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
                'group_id' => Field::dynamic('Group', 'zendesk.groups'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'group_id', 'The group');
        $this->zendesk($run, $config, 'DELETE', "/groups/{$id}");

        return ['deleted' => true, 'id' => (int) $id];
    }
}
