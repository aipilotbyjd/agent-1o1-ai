<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SentryRemoveMemberFromTeamNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_remove_member_from_team';
    }

    public function name(): string
    {
        return 'Sentry: Remove Member From Team';
    }

    public function description(): string
    {
        return 'Takes a member out of a team.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['member_id', 'team'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'member_id' => $this->memberField(),
                'team' => $this->teamField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $member = $this->pathId($config, 'member_id', 'The member');
        $team = $this->team($config);
        $this->sentry($run, $config, 'DELETE', '/api/0/organizations/'.$this->org($run, $config)."/members/{$member}/teams/{$team}/");

        return ['removed' => true, 'member_id' => $member, 'team' => $team];
    }
}
