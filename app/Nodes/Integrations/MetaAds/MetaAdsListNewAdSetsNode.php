<?php

namespace App\Nodes\Integrations\MetaAds;

class MetaAdsListNewAdSetsNode extends AbstractMetaAdsRecordTriggerNode
{
    public function type(): string
    {
        return 'meta_ads_list_new_ad_sets';
    }

    public function name(): string
    {
        return 'Meta Ads: List New Ad Sets';
    }

    public function description(): string
    {
        return 'Lists the ad sets created lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
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
        return true;
    }

    public function triggerName(): string
    {
        return 'Meta Ads: New ad set';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an ad set is created in the ad account. Checks every few minutes.';
    }
}
