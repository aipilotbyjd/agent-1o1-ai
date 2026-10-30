<?php

namespace App\Nodes\Integrations\LinkedInAds;

class LinkedInAdsListNewPageLeadsNode extends AbstractLinkedInAdsLeadTriggerNode
{
    public function type(): string
    {
        return 'linkedin_ads_list_new_page_leads';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: List New Page Leads';
    }

    public function description(): string
    {
        return 'Lists new leads from a company page. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function leadType(): string
    {
        return 'COMPANY';
    }

    public function triggerName(): string
    {
        return 'LinkedIn Ads: New page lead';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone sends a lead form from a company or product page. Checks every few minutes.';
    }
}
