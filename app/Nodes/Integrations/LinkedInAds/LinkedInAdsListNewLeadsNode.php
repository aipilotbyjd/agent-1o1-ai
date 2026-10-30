<?php

namespace App\Nodes\Integrations\LinkedInAds;

class LinkedInAdsListNewLeadsNode extends AbstractLinkedInAdsLeadTriggerNode
{
    public function type(): string
    {
        return 'linkedin_ads_list_new_leads';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: List New Ad Leads';
    }

    public function description(): string
    {
        return 'Lists new leads from ads, newest in the window. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function leadType(): string
    {
        return 'SPONSORED';
    }

    public function triggerName(): string
    {
        return 'LinkedIn Ads: New ad lead';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone sends a lead form from an ad. Checks every few minutes.';
    }
}
