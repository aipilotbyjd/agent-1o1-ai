<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FreshdeskGetAccountNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_account';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Account';
    }

    public function description(): string
    {
        return 'Reads the account\'s plan, agents and data center. Needs an admin.';
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
        return $this->freshdesk($run, $config, 'GET', '/account');
    }
}
