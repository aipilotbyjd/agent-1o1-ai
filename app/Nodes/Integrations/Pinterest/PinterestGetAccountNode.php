<?php

namespace App\Nodes\Integrations\Pinterest;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class PinterestGetAccountNode extends AbstractPinterestNode
{
    public function type(): string
    {
        return 'pinterest_get_account';
    }

    public function name(): string
    {
        return 'Pinterest: Get Account';
    }

    public function description(): string
    {
        return 'Reads the connected account and checks the connection works.';
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
        $account = $this->pinterest($run, $config, 'GET', '/user_account');

        return ['username' => $account['username'] ?? null, 'account_type' => $account['account_type'] ?? null, 'website' => $account['website_url'] ?? null, 'boards' => $account['board_count'] ?? null, 'pins' => $account['pin_count'] ?? null, 'followers' => $account['follower_count'] ?? null, 'monthly_views' => $account['monthly_views'] ?? null];
    }
}
