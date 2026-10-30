<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyGetLimitsNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_limits';
    }

    public function name(): string
    {
        return 'Apify: Get Limits';
    }

    public function description(): string
    {
        return 'Reads the account\'s limits and how much of them is used.';
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
        return ['limits' => $this->apify($run, $config, 'GET', '/users/me/limits')];
    }
}
