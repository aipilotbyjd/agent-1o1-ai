<?php

namespace App\Nodes\Integrations\LinkedInAds;

class LinkedInAdsListNewCampaignGroupsNode extends AbstractLinkedInAdsRecordTriggerNode
{
    public function type(): string
    {
        return 'linkedin_ads_list_new_campaign_groups';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: List New Campaign Groups';
    }

    public function description(): string
    {
        return 'Lists the campaign groups created lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function resource(): string
    {
        return 'adCampaignGroups';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'LinkedIn Ads: New campaign group';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a campaign group is created in the ad account. Checks every few minutes.';
    }
}
