<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsAddConversionToCampaignNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_add_conversion_to_campaign';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Add Conversion to Campaign';
    }

    public function description(): string
    {
        return 'Makes a campaign measure a conversion rule.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'campaign_id', 'conversion_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'campaign_id' => Field::dynamic('Campaign', 'linkedin_ads.campaigns', null, null, ['account_id']),
                'conversion_id' => Field::dynamic('Conversion rule', 'linkedin_ads.conversions', null, null, ['account_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $campaign = $this->urn($config, 'campaign_id', 'The campaign', 'sponsoredCampaign');
        $conversion = 'urn:lla:llaPartnerConversion:'.$this->numericId($config, 'conversion_id', 'The conversion rule');

        $this->linkedin($run, $config, 'PUT', '/campaignConversions/(campaign:'.rawurlencode($this->urn($config, 'campaign_id', 'The campaign', 'sponsoredCampaign')).',conversion:'.rawurlencode('urn:lla:llaPartnerConversion:'.$this->numericId($config, 'conversion_id', 'The conversion rule')).')', '', ['campaign' => $campaign, 'conversion' => $conversion]);

        return ['added' => true, 'campaign' => $campaign, 'conversion' => $conversion];
    }
}
