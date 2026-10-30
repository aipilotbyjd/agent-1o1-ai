<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetCompanyNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_company';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Company';
    }

    public function description(): string
    {
        return 'Reads a company by id, or says it was not found.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['company_id'],
            'properties' => [
                ...$this->credentialFields(),
                'company_id' => Field::dynamic('Company', 'freshdesk.companies'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'company_id', 'The company');

        return $this->findOne($run, $config, "/companies/{$id}", $id, $this->shapeCompany(...));
    }
}
