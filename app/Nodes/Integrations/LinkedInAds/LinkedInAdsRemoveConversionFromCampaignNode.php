<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsRemoveConversionFromCampaignNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_remove_conversion_from_campaign';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Remove Conversion from Campaign';
    }

    public function description(): string
    {
        return 'Stops a campaign measuring a conversion rule.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $this->linkedin($run, $config, 'DELETE', '/campaignConversions/(campaign:'.rawurlencode($this->urn($config, 'campaign_id', 'The campaign', 'sponsoredCampaign')).',conversion:'.rawurlencode('urn:lla:llaPartnerConversion:'.$this->numericId($config, 'conversion_id', 'The conversion rule')).')');

        return ['removed' => true];
    }
}
