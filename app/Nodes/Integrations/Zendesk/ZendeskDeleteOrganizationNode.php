<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskDeleteOrganizationNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_delete_organization';
    }

    public function name(): string
    {
        return 'Zendesk: Delete Organization';
    }

    public function description(): string
    {
        return 'Deletes an organization.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['organization_id'],
            'properties' => [
                ...$this->credentialFields(),
                'organization_id' => Field::text('Organization id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'organization_id', 'The organization');
        $this->zendesk($run, $config, 'DELETE', "/organizations/{$id}");

        return ['deleted' => true, 'id' => (int) $id];
    }
}
