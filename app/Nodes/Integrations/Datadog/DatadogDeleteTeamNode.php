<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DatadogDeleteTeamNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_delete_team';
    }

    public function name(): string
    {
        return 'Datadog: Delete Team';
    }

    public function description(): string
    {
        return 'Removes a team.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['team_id'],
            'properties' => [
                ...$this->credentialFields(),
                'team_id' => $this->teamField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'team_id', 'The team');
        $this->datadog($run, $config, 'DELETE', "/api/v2/team/{$id}");

        return ['deleted' => true, 'id' => rawurldecode($id)];
    }
}
