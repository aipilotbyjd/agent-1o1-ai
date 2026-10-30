<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsGetCampaignGroupNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_get_campaign_group';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Get Campaign Group';
    }

    public function description(): string
    {
        return 'Reads one campaign group.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'campaign_group_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'campaign_group_id' => Field::dynamic('Campaign group', 'linkedin_ads.campaign_groups', null, null, ['account_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['campaign_group' => $this->shapeCampaignGroup($this->linkedin($run, $config, 'GET', '/adAccounts/'.$this->numericId($config, 'account_id', 'The ad account').'/adCampaignGroups/'.$this->numericId($config, 'campaign_group_id', 'The campaign group')))];
    }
}
