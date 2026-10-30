<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskGetOrganizationNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_get_organization';
    }

    public function name(): string
    {
        return 'Zendesk: Get Organization';
    }

    public function description(): string
    {
        return 'Reads an organization by id, or says it was not found.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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

        return $this->findOne($run, $config, "/organizations/{$id}", 'organization', $id, $this->shapeOrganization(...));
    }
}
