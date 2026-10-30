<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FreshdeskListRolesNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_roles';
    }

    public function name(): string
    {
        return 'Freshdesk: List Roles';
    }

    public function description(): string
    {
        return 'Lists the agent roles.';
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
        $rows = $this->freshdesk($run, $config, 'GET', '/roles');

        return ['roles' => array_values($rows), 'count' => count($rows)];
    }
}
