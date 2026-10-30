<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SentryAddMemberToTeamNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_add_member_to_team';
    }

    public function name(): string
    {
        return 'Sentry: Add Member To Team';
    }

    public function description(): string
    {
        return 'Adds a member of the organization to a team.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
        return $this->shapeTeam($this->sentry($run, $config, 'POST', '/api/0/organizations/'.$this->org($run, $config).'/members/'.$this->pathId($config, 'member_id', 'The member').'/teams/'.$this->team($config).'/'));
    }
}
