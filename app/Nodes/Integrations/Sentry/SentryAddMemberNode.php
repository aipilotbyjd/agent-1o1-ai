<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SentryAddMemberNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_add_member';
    }

    public function name(): string
    {
        return 'Sentry: Add Member';
    }

    public function description(): string
    {
        return 'Invites someone to an organization.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'email' => Field::text('Email', null, 'name@example.com'),
                'role' => Field::select('Role', ['billing' => 'Billing', 'member' => 'Member', 'manager' => 'Manager', 'owner' => 'Owner', 'admin' => 'Admin'], null, 'member'),
                'teams' => Field::list('Teams', 'Team slugs.'),
                'send_invite' => Field::advanced(Field::boolean('Send the invitation email', null, true)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $email = $this->requiredString($config, 'email', 'The email');

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('That is not an email address.');
        }

        $body = ['email' => $email, ...$this->filledOf($config, ['role' => 'orgRole'])];

        if ($this->items($config, 'teams') !== []) {
            $body['teamRoles'] = array_map(fn (string $team): array => ['teamSlug' => $team, 'role' => null], $this->items($config, 'teams'));
        }

        if (($config['send_invite'] ?? true) === false) {
            $body['sendInvite'] = false;
        }

        return $this->shapeMember($this->sentry($run, $config, 'POST', '/api/0/organizations/'.$this->org($run, $config).'/members/', [], $body));
    }
}
