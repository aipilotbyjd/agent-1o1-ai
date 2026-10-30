<?php

namespace App\Nodes\Integrations\LinkedInAds;

class LinkedInAdsListNewCreativesNode extends AbstractLinkedInAdsRecordTriggerNode
{
    public function type(): string
    {
        return 'linkedin_ads_list_new_creatives';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: List New Creatives';
    }

    public function description(): string
    {
        return 'Lists the creatives created lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function resource(): string
    {
        return 'creatives';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'LinkedIn Ads: New creative';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a creative is created in the ad account. Checks every few minutes.';
    }
}
