<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomGetTeamNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_get_team';
    }

    public function name(): string
    {
        return 'Intercom: Get Team';
    }

    public function description(): string
    {
        return 'Reads a team by id.';
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
                'team_id' => Field::dynamic('Team', 'intercom.teams'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'team_id', 'The team');

        return $this->findOne($run, $config, "/teams/{$id}", $id);
    }
}
