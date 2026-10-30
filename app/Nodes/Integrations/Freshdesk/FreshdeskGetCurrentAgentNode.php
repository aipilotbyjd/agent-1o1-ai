<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FreshdeskGetCurrentAgentNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_current_agent';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Current Agent';
    }

    public function description(): string
    {
        return 'Reads the agent this API key belongs to.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->freshdesk($run, $config, 'GET', '/agents/me');
    }
}
