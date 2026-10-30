<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskUpdateUserNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_update_user';
    }

    public function name(): string
    {
        return 'Zendesk: Update User';
    }

    public function description(): string
    {
        return 'Changes a person. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['user_id'],
            'properties' => [
                ...$this->credentialFields(),
                'user_id' => Field::text('User id'),
                'name' => Field::advanced(Field::text('Name')),
                'email' => Field::advanced(Field::text('Email')),
                'role' => Field::advanced(Field::select('Role', ['end-user' => 'End user', 'agent' => 'Agent', 'admin' => 'Admin'])),
                'phone' => Field::advanced(Field::text('Phone')),
                'organization_id' => Field::advanced(Field::dynamic('Organization', 'zendesk.organizations')),
                'external_id' => Field::advanced(Field::text('External id')),
                'tags' => Field::advanced(Field::text('Tags', 'Separated by commas. Replaces the tags.')),
                'notes' => Field::advanced(Field::textarea('Notes')),
                'details' => Field::advanced(Field::textarea('Details')),
                'user_fields' => Field::advanced(Field::json('Custom fields (JSON)', 'By field key, e.g. {"plan": "gold"}.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'user_id', 'The user');
        $body = $this->userBody($config);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->shapeUser((array) ($this->zendesk($run, $config, 'PUT', "/users/{$id}", [], ['user' => $body])['user'] ?? []));
    }
}
