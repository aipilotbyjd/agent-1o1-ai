<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskDeleteCompanyNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_delete_company';
    }

    public function name(): string
    {
        return 'Freshdesk: Delete Company';
    }

    public function description(): string
    {
        return 'Deletes a company. Its contacts stay.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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

        return $this->destroy($run, $config, "/companies/{$id}", $id);
    }
}
