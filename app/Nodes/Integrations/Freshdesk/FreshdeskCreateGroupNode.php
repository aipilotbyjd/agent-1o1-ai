<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskCreateGroupNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_create_group';
    }

    public function name(): string
    {
        return 'Freshdesk: Create Group';
    }

    public function description(): string
    {
        return 'Creates an agent group.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name'),
                'description' => Field::advanced(Field::textarea('Description')),
                'agent_ids' => Field::advanced(Field::text('Agent ids', 'Separated by commas.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->freshdesk($run, $config, 'POST', '/groups', [], $this->present(['name' => $this->requiredString($config, 'name', 'The name'), 'description' => $this->text($config, 'description'), 'agent_ids' => $this->idList($config, 'agent_ids', 'The agents', false)]));
    }
}
