<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogAddTeamMemberNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_add_team_member';
    }

    public function name(): string
    {
        return 'Datadog: Add Team Member';
    }

    public function description(): string
    {
        return 'Adds a user to a team.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
                'admin' => Field::boolean('Make them a team admin'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $team = $this->pathId($config, 'team_id', 'The team');
        $user = $this->requiredString($config, 'user_id', 'The user');
        $data = ['type' => 'team_memberships', 'relationships' => ['user' => ['data' => ['id' => $user, 'type' => 'users']], 'team' => ['data' => ['id' => rawurldecode($team), 'type' => 'team']]]];

        if (($config['admin'] ?? false)) {
            $data['attributes'] = ['role' => 'admin'];
        }

        return $this->flatten((array) ($this->datadog($run, $config, 'POST', "/api/v2/team/{$team}/memberships", [], ['data' => $data])['data'] ?? []));
    }
}
