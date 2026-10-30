<?php

namespace App\Nodes\Integrations\MetaAds;

class MetaAdsListChangedCampaignsNode extends AbstractMetaAdsRecordTriggerNode
{
    public function type(): string
    {
        return 'meta_ads_list_changed_campaigns';
    }

    public function name(): string
    {
        return 'Meta Ads: List Changed Campaigns';
    }

    public function description(): string
    {
        return 'Lists the campaigns changed lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
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
        return false;
    }

    public function triggerName(): string
    {
        return 'Meta Ads: Campaign changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a campaign is changed in the ad account. Checks every few minutes.';
    }
}
