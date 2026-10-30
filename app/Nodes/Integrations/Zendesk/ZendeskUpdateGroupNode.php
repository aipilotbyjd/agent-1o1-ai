<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskUpdateGroupNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_update_group';
    }

    public function name(): string
    {
        return 'Zendesk: Update Group';
    }

    public function description(): string
    {
        return 'Changes an agent group\'s name or description.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['group_id'],
            'properties' => [
                ...$this->credentialFields(),
                'group_id' => Field::dynamic('Group', 'zendesk.groups'),
                'name' => Field::advanced(Field::text('Name')),
                'description' => Field::advanced(Field::text('Description')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'group_id', 'The group');
        $body = $this->present(['name' => $this->text($config, 'name'), 'description' => $this->text($config, 'description')]);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->shapeGroup((array) ($this->zendesk($run, $config, 'PUT', "/groups/{$id}", [], ['group' => $body])['group'] ?? []));
    }
}
