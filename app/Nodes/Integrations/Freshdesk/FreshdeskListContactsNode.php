<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskListContactsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_contacts';
    }

    public function name(): string
    {
        return 'Freshdesk: List Contacts';
    }

    public function description(): string
    {
        return 'Lists contacts, optionally by email, phone, company or state.';
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
                'email' => Field::advanced(Field::text('Email')),
                'mobile' => Field::advanced(Field::text('Mobile')),
                'phone' => Field::advanced(Field::text('Phone')),
                'company_id' => Field::advanced(Field::dynamic('Company', 'freshdesk.companies')),
                'state' => Field::advanced(Field::select('State', ['blocked' => 'Blocked', 'deleted' => 'Deleted', 'unverified' => 'Unverified', 'verified' => 'Verified'])),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listing($run, $config, '/contacts', 'contacts', ['email' => $this->text($config, 'email'), 'mobile' => $this->text($config, 'mobile'), 'phone' => $this->text($config, 'phone'), 'company_id' => $this->optionalId($config, 'company_id', 'The company'), 'state' => $this->text($config, 'state')], $this->shapeContact(...));
    }
}
