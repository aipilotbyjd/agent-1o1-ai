<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsListCampaignConversionsNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_list_campaign_conversions';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: List Campaign Conversions';
    }

    public function description(): string
    {
        return 'Lists the conversion rules a campaign measures.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        $query = 'q=campaigns&campaigns='.self::restliList([$this->urn($config, 'campaign_id', 'The campaign', 'sponsoredCampaign')]);

        return $this->indexedElements($this->linkedin($run, $config, 'GET', '/campaignConversions', $query), 'campaign_conversions');
    }
}
