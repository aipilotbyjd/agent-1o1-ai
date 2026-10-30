<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DatadogGetTeamNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_team';
    }

    public function name(): string
    {
        return 'Datadog: Get Team';
    }

    public function description(): string
    {
        return 'Gets a team.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        $team = $this->find($run, $config, '/api/v2/team/'.$this->pathId($config, 'team_id', 'The team'));

        return $team === null ? ['found' => false] : ['found' => true, ...$this->flatten((array) ($team['data'] ?? []))];
    }
}
