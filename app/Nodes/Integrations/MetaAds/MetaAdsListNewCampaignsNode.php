<?php

namespace App\Nodes\Integrations\MetaAds;

class MetaAdsListNewCampaignsNode extends AbstractMetaAdsRecordTriggerNode
{
    public function type(): string
    {
        return 'meta_ads_list_new_campaigns';
    }

    public function name(): string
    {
        return 'Meta Ads: List New Campaigns';
    }

    public function description(): string
    {
        return 'Lists the campaigns created lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function edge(): string
    {
        return 'campaigns';
    }

    protected function fields(): string
    {
        return self::CAMPAIGN_FIELDS;
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Meta Ads: New campaign';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a campaign is created in the ad account. Checks every few minutes.';
    }
}
