<?php

namespace App\Nodes\Integrations\Zendesk;

class ZendeskListNewOrganizationsNode extends AbstractZendeskSearchTriggerNode
{
    public function type(): string
    {
        return 'zendesk_list_new_organizations';
    }

    public function name(): string
    {
        return 'Zendesk: List New Organizations';
    }

    public function description(): string
    {
        return 'Lists the organizations added lately, newest first. As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function resource(): string
    {
        return 'organization';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'organizations';
    }

    public function triggerName(): string
    {
        return 'Zendesk: New organization';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an organization is added in Zendesk. Instant, with a check every few minutes.';
    }
}
