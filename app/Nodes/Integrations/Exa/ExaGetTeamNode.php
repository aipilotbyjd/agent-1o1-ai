<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExaGetTeamNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_get_team';
    }

    public function name(): string
    {
        return 'Exa: Get Team';
    }

    public function description(): string
    {
        return 'Reads the team the key belongs to, with its concurrency limits.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['team' => $this->exa($run, $config, 'GET', '/v0/teams/me')];
    }
}
