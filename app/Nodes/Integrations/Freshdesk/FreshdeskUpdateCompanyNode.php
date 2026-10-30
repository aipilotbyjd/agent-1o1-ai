<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskUpdateCompanyNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_update_company';
    }

    public function name(): string
    {
        return 'Freshdesk: Update Company';
    }

    public function description(): string
    {
        return 'Changes a company. Only what is filled in changes.';
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
                'company_id' => Field::dynamic('Company', 'freshdesk.companies'),
                ...$this->companyFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'company_id', 'The company');
        $body = $this->companyBody($config);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->shapeCompany($this->freshdesk($run, $config, 'PUT', "/companies/{$id}", [], $body));
    }
}
