<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class LinkedInAdsListMyAccountsNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_list_my_accounts';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: List My Ad Accounts';
    }

    public function description(): string
    {
        return 'Lists the ad accounts the connected member can use and their role on each, and checks the connection works.';
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
        return $this->elements($this->linkedin($run, $config, 'GET', '/adAccountUsers', 'q=authenticatedUser'), 'account_users');
    }
}
