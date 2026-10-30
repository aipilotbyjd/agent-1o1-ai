<?php

namespace App\Nodes\Integrations\LinkedInAds;

class LinkedInAdsListChangedCreativesNode extends AbstractLinkedInAdsRecordTriggerNode
{
    public function type(): string
    {
        return 'linkedin_ads_list_changed_creatives';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: List Changed Creatives';
    }

    public function description(): string
    {
        return 'Lists the creatives changed lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function resource(): string
    {
        return 'creatives';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'LinkedIn Ads: Creative changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a creative is changed in the ad account. Checks every few minutes.';
    }
}
