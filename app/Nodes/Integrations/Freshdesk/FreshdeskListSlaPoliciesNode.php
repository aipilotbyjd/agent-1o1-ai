<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FreshdeskListSlaPoliciesNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_sla_policies';
    }

    public function name(): string
    {
        return 'Freshdesk: List SLA Policies';
    }

    public function description(): string
    {
        return 'Lists the account\'s SLA policies.';
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
        $rows = $this->freshdesk($run, $config, 'GET', '/sla_policies');

        return ['sla_policies' => array_values($rows), 'count' => count($rows)];
    }
}
