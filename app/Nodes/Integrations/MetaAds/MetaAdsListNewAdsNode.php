<?php

namespace App\Nodes\Integrations\MetaAds;

class MetaAdsListNewAdsNode extends AbstractMetaAdsRecordTriggerNode
{
    public function type(): string
    {
        return 'meta_ads_list_new_ads';
    }

    public function name(): string
    {
        return 'Meta Ads: List New Ads';
    }

    public function description(): string
    {
        return 'Lists the ads created lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
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
        return true;
    }

    public function triggerName(): string
    {
        return 'Meta Ads: New ad';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an ad is created in the ad account. Checks every few minutes.';
    }
}
