<?php

namespace App\Nodes\Integrations\MetaAds;

class MetaAdsListChangedAdSetsNode extends AbstractMetaAdsRecordTriggerNode
{
    public function type(): string
    {
        return 'meta_ads_list_changed_ad_sets';
    }

    public function name(): string
    {
        return 'Meta Ads: List Changed Ad Sets';
    }

    public function description(): string
    {
        return 'Lists the ad sets changed lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function edge(): string
    {
        return 'adsets';
    }

    protected function fields(): string
    {
        return self::AD_SET_FIELDS;
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Meta Ads: Ad set changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an ad set is changed in the ad account. Checks every few minutes.';
    }
}
