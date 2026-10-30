<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class LinkedInAdsListAccountUsersNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_list_account_users';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: List Account Users';
    }

    public function description(): string
    {
        return 'Lists who has access to an ad account, and in what role.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->elements($this->linkedin($run, $config, 'GET', '/adAccountUsers', 'q=accounts&accounts='.rawurlencode($this->urn($config, 'account_id', 'The ad account', 'sponsoredAccount'))), 'account_users');
    }
}
