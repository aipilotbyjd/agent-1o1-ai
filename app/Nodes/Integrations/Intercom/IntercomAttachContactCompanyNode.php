<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomAttachContactCompanyNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_attach_contact_company';
    }

    public function name(): string
    {
        return 'Intercom: Attach Company To Contact';
    }

    public function description(): string
    {
        return 'Adds a contact to a company.';
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

        return $this->shapeCompany($this->intercom($run, $config, 'POST', "/contacts/{$id}/companies", [], ['id' => $this->safeId($config, 'company_id', 'The company')]));
    }
}
