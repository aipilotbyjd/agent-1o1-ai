<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SentryGetMemberNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_get_member';
    }

    public function name(): string
    {
        return 'Sentry: Get Member';
    }

    public function description(): string
    {
        return 'Gets a member of an organization.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        $member = $this->find($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/members/'.$this->pathId($config, 'member_id', 'The member').'/');

        return $member === null ? ['found' => false] : ['found' => true, ...$this->shapeMember($member), 'teams' => $member['teams'] ?? null];
    }
}
