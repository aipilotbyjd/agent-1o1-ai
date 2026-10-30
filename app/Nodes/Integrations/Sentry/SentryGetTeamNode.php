<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SentryGetTeamNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_get_team';
    }

    public function name(): string
    {
        return 'Sentry: Get Team';
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
            'required' => ['team'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'team' => $this->teamField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $team = $this->find($run, $config, '/api/0/teams/'.$this->org($run, $config).'/'.$this->team($config).'/');

        return $team === null ? ['found' => false] : ['found' => true, ...$this->shapeTeam($team)];
    }
}
