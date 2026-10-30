<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyGetAccountNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_account';
    }

    public function name(): string
    {
        return 'Apify: Get Account';
    }

    public function description(): string
    {
        return 'Reads your Apify account: name, plan and proxy settings.';
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
        return ['account' => $this->apify($run, $config, 'GET', '/users/me')];
    }
}
