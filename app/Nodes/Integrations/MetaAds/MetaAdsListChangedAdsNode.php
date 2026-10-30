<?php

namespace App\Nodes\Integrations\MetaAds;

class MetaAdsListChangedAdsNode extends AbstractMetaAdsRecordTriggerNode
{
    public function type(): string
    {
        return 'meta_ads_list_changed_ads';
    }

    public function name(): string
    {
        return 'Meta Ads: List Changed Ads';
    }

    public function description(): string
    {
        return 'Lists the ads changed lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function edge(): string
    {
        return 'ads';
    }

    protected function fields(): string
    {
        return self::AD_FIELDS;
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Meta Ads: Ad changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an ad is changed in the ad account. Checks every few minutes.';
    }
}
