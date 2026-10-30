<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FreshdeskCreateCompanyNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_create_company';
    }

    public function name(): string
    {
        return 'Freshdesk: Create Company';
    }

    public function description(): string
    {
        return 'Creates a company.';
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
                ...$this->companyFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->requiredString($config, 'name', 'The name');

        return $this->shapeCompany($this->freshdesk($run, $config, 'POST', '/companies', [], $this->companyBody($config)));
    }
}
