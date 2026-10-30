<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskDeleteAgentNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_delete_agent';
    }

    public function name(): string
    {
        return 'Freshdesk: Delete Agent';
    }

    public function description(): string
    {
        return 'Removes an agent, who becomes a contact.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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

        return $this->destroy($run, $config, "/agents/{$id}", $id);
    }
}
