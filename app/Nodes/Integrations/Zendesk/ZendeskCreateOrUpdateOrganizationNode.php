<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskCreateOrUpdateOrganizationNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_create_or_update_organization';
    }

    public function name(): string
    {
        return 'Zendesk: Create or Update Organization';
    }

    public function description(): string
    {
        return 'Updates the organization with this id or external id, or creates it.';
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
                'name' => Field::text('Name'),
                'organization_id' => Field::advanced(Field::text('Organization id', 'Updates this one.')),
                ...$this->organizationFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = [...$this->organizationBody($config), ...$this->present(['id' => $this->optionalId($config, 'organization_id', 'The organization')])];

        return $this->shapeOrganization((array) ($this->zendesk($run, $config, 'POST', '/organizations/create_or_update', [], ['organization' => $body])['organization'] ?? []));
    }
}
