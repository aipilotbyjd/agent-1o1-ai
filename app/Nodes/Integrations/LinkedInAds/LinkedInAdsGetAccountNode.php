<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class LinkedInAdsGetAccountNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_get_account';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Get Ad Account';
    }

    public function description(): string
    {
        return 'Reads one ad account: its name, currency, status and more.';
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
        return $this->linkedin($run, $config, 'GET', '/adAccounts/'.$this->numericId($config, 'account_id', 'The ad account'));
    }
}
