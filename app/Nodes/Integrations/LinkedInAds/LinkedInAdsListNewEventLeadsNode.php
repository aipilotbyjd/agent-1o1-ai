<?php

namespace App\Nodes\Integrations\LinkedInAds;

class LinkedInAdsListNewEventLeadsNode extends AbstractLinkedInAdsLeadTriggerNode
{
    public function type(): string
    {
        return 'linkedin_ads_list_new_event_leads';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: List New Event Registrations';
    }

    public function description(): string
    {
        return 'Lists new event registration form responses. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function leadType(): string
    {
        return 'EVENT';
    }

    public function triggerName(): string
    {
        return 'LinkedIn Ads: New event registration';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone registers through an event form. Checks every few minutes.';
    }
}
