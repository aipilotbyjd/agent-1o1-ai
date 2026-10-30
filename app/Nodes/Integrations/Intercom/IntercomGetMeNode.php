<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class IntercomGetMeNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_get_me';
    }

    public function name(): string
    {
        return 'Intercom: Get Authenticated User';
    }

    public function description(): string
    {
        return 'Reads the teammate and workspace this token belongs to.';
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
        return $this->intercom($run, $config, 'GET', '/me');
    }
}
