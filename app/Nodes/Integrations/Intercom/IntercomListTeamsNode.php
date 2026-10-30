<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class IntercomListTeamsNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_list_teams';
    }

    public function name(): string
    {
        return 'Intercom: List Teams';
    }

    public function description(): string
    {
        return 'Lists the workspace\'s teams.';
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
        $rows = (array) ($this->intercom($run, $config, 'GET', '/teams')['teams'] ?? []);

        return ['teams' => $rows, 'count' => count($rows)];
    }
}
