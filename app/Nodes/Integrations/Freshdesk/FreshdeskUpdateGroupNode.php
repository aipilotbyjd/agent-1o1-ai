<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskUpdateGroupNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_update_group';
    }

    public function name(): string
    {
        return 'Freshdesk: Update Group';
    }

    public function description(): string
    {
        return 'Changes an agent group. Only what is filled in changes.';
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
                'group_id' => Field::dynamic('Group', 'freshdesk.groups'),
                'name' => Field::advanced(Field::text('Name')),
                'description' => Field::advanced(Field::textarea('Description')),
                'agent_ids' => Field::advanced(Field::text('Agent ids', 'Separated by commas. Replaces the agents.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'group_id', 'The group');
        $body = $this->present(['name' => $this->text($config, 'name'), 'description' => $this->text($config, 'description'), 'agent_ids' => $this->idList($config, 'agent_ids', 'The agents', false)]);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->freshdesk($run, $config, 'PUT', "/groups/{$id}", [], $body);
    }
}
