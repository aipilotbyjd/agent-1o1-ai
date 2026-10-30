<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetAgentNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_agent';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Agent';
    }

    public function description(): string
    {
        return 'Reads an agent by id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['agent_id'],
            'properties' => [
                ...$this->credentialFields(),
                'agent_id' => Field::dynamic('Agent', 'freshdesk.agents'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'agent_id', 'The agent');

        return $this->findOne($run, $config, "/agents/{$id}", $id);
    }
}
