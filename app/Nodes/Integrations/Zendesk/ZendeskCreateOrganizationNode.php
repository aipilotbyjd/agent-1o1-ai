<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskCreateOrganizationNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_create_organization';
    }

    public function name(): string
    {
        return 'Zendesk: Create Organization';
    }

    public function description(): string
    {
        return 'Creates an organization.';
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
                ...$this->organizationFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeOrganization((array) ($this->zendesk($run, $config, 'POST', '/organizations', [], ['organization' => $this->organizationBody($config)])['organization'] ?? []));
    }
}
