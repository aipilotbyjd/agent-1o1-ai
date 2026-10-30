<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskFindOrCreateOrganizationNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_find_or_create_organization';
    }

    public function name(): string
    {
        return 'Zendesk: Find or Create Organization';
    }

    public function description(): string
    {
        return 'Finds the organization with this exact name, or creates it.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $name = $this->requiredString($config, 'name', 'The name');
        $rows = (array) ($this->zendesk($run, $config, 'GET', '/organizations/search', ['name' => $name])['organizations'] ?? []);
        if ($rows !== []) {
            return ['created' => false, ...$this->shapeOrganization((array) $rows[0])];
        }

        return ['created' => true, ...$this->shapeOrganization((array) ($this->zendesk($run, $config, 'POST', '/organizations', [], ['organization' => ['name' => $name]])['organization'] ?? []))];
    }
}
