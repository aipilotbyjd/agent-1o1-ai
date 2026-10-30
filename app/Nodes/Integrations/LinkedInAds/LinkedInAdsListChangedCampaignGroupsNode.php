<?php

namespace App\Nodes\Integrations\LinkedInAds;

class LinkedInAdsListChangedCampaignGroupsNode extends AbstractLinkedInAdsRecordTriggerNode
{
    public function type(): string
    {
        return 'linkedin_ads_list_changed_campaign_groups';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: List Changed Campaign Groups';
    }

    public function description(): string
    {
        return 'Lists the campaign groups changed lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function resource(): string
    {
        return 'adCampaignGroups';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'LinkedIn Ads: Campaign group changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a campaign group is changed in the ad account. Checks every few minutes.';
    }
}
