<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskUpdateOrganizationNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_update_organization';
    }

    public function name(): string
    {
        return 'Zendesk: Update Organization';
    }

    public function description(): string
    {
        return 'Changes an organization. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['organization_id'],
            'properties' => [
                ...$this->credentialFields(),
                'organization_id' => Field::text('Organization id'),
                'name' => Field::advanced(Field::text('Name')),
                ...$this->organizationFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'organization_id', 'The organization');
        $body = $this->organizationBody($config);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->shapeOrganization((array) ($this->zendesk($run, $config, 'PUT', "/organizations/{$id}", [], ['organization' => $body])['organization'] ?? []));
    }
}
