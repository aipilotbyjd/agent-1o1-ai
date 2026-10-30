<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskGetOrganizationRelatedNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_get_organization_related';
    }

    public function name(): string
    {
        return 'Zendesk: Get Organization Related Information';
    }

    public function description(): string
    {
        return 'Counts an organization\'s tickets and people.';
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

        return $this->findOne($run, $config, "/organizations/{$id}/related", 'organization_related', $id);
    }
}
