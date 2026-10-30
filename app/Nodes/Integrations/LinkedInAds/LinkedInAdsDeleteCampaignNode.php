<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsDeleteCampaignNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_delete_campaign';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Delete Campaign';
    }

    public function description(): string
    {
        return 'Deletes a draft campaign at once, and marks one that has been live for deletion. This cannot be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'campaign_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'campaign_id' => Field::dynamic('Campaign', 'linkedin_ads.campaigns', null, null, ['account_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['result' => $this->remove($run, $config, '/adAccounts/'.$this->numericId($config, 'account_id', 'The ad account').'/adCampaigns/'.$this->numericId($config, 'campaign_id', 'The campaign'))];
    }
}
