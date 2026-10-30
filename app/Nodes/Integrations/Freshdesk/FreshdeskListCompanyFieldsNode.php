<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FreshdeskListCompanyFieldsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_company_fields';
    }

    public function name(): string
    {
        return 'Freshdesk: List Company Fields';
    }

    public function description(): string
    {
        return 'Lists the system and custom fields of companies.';
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
        $rows = $this->freshdesk($run, $config, 'GET', '/company_fields');

        return ['company_fields' => array_values($rows), 'count' => count($rows)];
    }
}
