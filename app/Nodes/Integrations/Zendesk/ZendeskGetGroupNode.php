<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskGetGroupNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_get_group';
    }

    public function name(): string
    {
        return 'Zendesk: Get Group';
    }

    public function description(): string
    {
        return 'Reads an agent group by id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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

        return $this->findOne($run, $config, "/groups/{$id}", 'group', $id, $this->shapeGroup(...));
    }
}
