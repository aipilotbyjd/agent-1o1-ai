<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryUpdateMemberRoleNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_update_member_role';
    }

    public function name(): string
    {
        return 'Sentry: Update Member Role';
    }

    public function description(): string
    {
        return 'Changes a member\'s organization role.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['member_id', 'role'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'member_id' => $this->memberField(),
                'role' => Field::select('Role', ['billing' => 'Billing', 'member' => 'Member', 'manager' => 'Manager', 'owner' => 'Owner', 'admin' => 'Admin'], null, 'member'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeMember($this->sentry($run, $config, 'PUT', '/api/0/organizations/'.$this->org($run, $config).'/members/'.$this->pathId($config, 'member_id', 'The member').'/', [], ['orgRole' => $this->requiredString($config, 'role', 'The role')]));
    }
}
