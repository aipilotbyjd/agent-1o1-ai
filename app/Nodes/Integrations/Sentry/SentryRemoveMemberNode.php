<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SentryRemoveMemberNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_remove_member';
    }

    public function name(): string
    {
        return 'Sentry: Remove Member';
    }

    public function description(): string
    {
        return 'Removes a member from an organization.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['member_id'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'member_id' => $this->memberField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $member = $this->pathId($config, 'member_id', 'The member');
        $this->sentry($run, $config, 'DELETE', '/api/0/organizations/'.$this->org($run, $config)."/members/{$member}/");

        return ['removed' => true, 'id' => $member];
    }
}
