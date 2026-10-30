<?php

namespace App\Nodes\Integrations\LinkedInAds;

class LinkedInAdsListChangedCampaignsNode extends AbstractLinkedInAdsRecordTriggerNode
{
    public function type(): string
    {
        return 'linkedin_ads_list_changed_campaigns';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: List Changed Campaigns';
    }

    public function description(): string
    {
        return 'Lists the campaigns changed lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function resource(): string
    {
        return 'adCampaigns';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'LinkedIn Ads: Campaign changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a campaign is changed in the ad account. Checks every few minutes.';
    }
}
