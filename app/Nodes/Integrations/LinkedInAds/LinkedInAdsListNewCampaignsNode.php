<?php

namespace App\Nodes\Integrations\LinkedInAds;

class LinkedInAdsListNewCampaignsNode extends AbstractLinkedInAdsRecordTriggerNode
{
    public function type(): string
    {
        return 'linkedin_ads_list_new_campaigns';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: List New Campaigns';
    }

    public function description(): string
    {
        return 'Lists the campaigns created lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function resource(): string
    {
        return 'adCampaigns';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'LinkedIn Ads: New campaign';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a campaign is created in the ad account. Checks every few minutes.';
    }
}
