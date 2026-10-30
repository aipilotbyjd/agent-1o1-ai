<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class IntercomListAdminsNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_list_admins';
    }

    public function name(): string
    {
        return 'Intercom: List Admins';
    }

    public function description(): string
    {
        return 'Lists the workspace\'s teammates.';
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
        $rows = (array) ($this->intercom($run, $config, 'GET', '/admins')['admins'] ?? []);

        return ['admins' => $rows, 'count' => count($rows)];
    }
}
