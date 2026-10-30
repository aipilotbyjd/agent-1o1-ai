<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomDetachContactCompanyNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_detach_contact_company';
    }

    public function name(): string
    {
        return 'Intercom: Detach Company From Contact';
    }

    public function description(): string
    {
        return 'Removes a contact from a company.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact_id', 'company_id'],
            'properties' => [
                ...$this->credentialFields(),
                'contact_id' => Field::text('Contact id'),
                'company_id' => Field::text('Company id', 'Intercom\'s id of the company.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'contact_id', 'The contact');
        $company = $this->safeId($config, 'company_id', 'The company');

        return $this->shapeCompany($this->intercom($run, $config, 'DELETE', "/contacts/{$id}/companies/{$company}"));
    }
}
