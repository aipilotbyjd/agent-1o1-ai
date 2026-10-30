<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskCreateUserNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_create_user';
    }

    public function name(): string
    {
        return 'Zendesk: Create User';
    }

    public function description(): string
    {
        return 'Creates a person: an end user, or an agent when the role says so.';
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
                'email' => Field::text('Email', null, 'ada@example.com'),
                'role' => Field::advanced(Field::select('Role', ['end-user' => 'End user', 'agent' => 'Agent', 'admin' => 'Admin'])),
                'phone' => Field::advanced(Field::text('Phone')),
                'organization_id' => Field::advanced(Field::dynamic('Organization', 'zendesk.organizations')),
                'external_id' => Field::advanced(Field::text('External id')),
                'tags' => Field::advanced(Field::text('Tags', 'Separated by commas.')),
                'notes' => Field::advanced(Field::textarea('Notes')),
                'details' => Field::advanced(Field::textarea('Details')),
                'user_fields' => Field::advanced(Field::json('Custom fields (JSON)', 'By field key, e.g. {"plan": "gold"}.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeUser((array) ($this->zendesk($run, $config, 'POST', '/users', [], ['user' => $this->userBody($config)])['user'] ?? []));
    }
}
