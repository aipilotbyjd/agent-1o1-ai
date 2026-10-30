<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomCreateCompanyNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_create_company';
    }

    public function name(): string
    {
        return 'Intercom: Create or Update Company';
    }

    public function description(): string
    {
        return 'Creates a company, or changes the one with this company id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['company_id'],
            'properties' => [
                ...$this->credentialFields(),
                'company_id' => Field::text('Your company id', 'Your own id for the company. Intercom finds it by this.'),
                ...$this->companyFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeCompany($this->intercom($run, $config, 'POST', '/companies', [], ['company_id' => $this->requiredString($config, 'company_id', 'The company id'), ...$this->companyBody($config)]));
    }
}
