<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DatadogRemoveTeamMemberNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_remove_team_member';
    }

    public function name(): string
    {
        return 'Datadog: Remove Team Member';
    }

    public function description(): string
    {
        return 'Removes a user from a team.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['team_id', 'user_id'],
            'properties' => [
                ...$this->credentialFields(),
                'team_id' => $this->teamField(),
                'user_id' => $this->userField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $team = $this->pathId($config, 'team_id', 'The team');
        $user = $this->pathId($config, 'user_id', 'The user');
        $this->datadog($run, $config, 'DELETE', "/api/v2/team/{$team}/memberships/{$user}");

        return ['removed' => true, 'team_id' => rawurldecode($team), 'user_id' => rawurldecode($user)];
    }
}
