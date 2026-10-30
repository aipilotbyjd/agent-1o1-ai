<?php

namespace App\Nodes\Integrations\Zendesk;

class ZendeskListUpdatedOrganizationsNode extends AbstractZendeskSearchTriggerNode
{
    public function type(): string
    {
        return 'zendesk_list_updated_organizations';
    }

    public function name(): string
    {
        return 'Zendesk: List Updated Organizations';
    }

    public function description(): string
    {
        return 'Lists the organizations changed lately, newest first. As a trigger it starts the flow for each change. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'organization';
    }

    protected function stamp(): string
    {
        return 'updated';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'organizations';
    }

    public function triggerName(): string
    {
        return 'Zendesk: Updated organization';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an organization changes in Zendesk. Checked every few minutes.';
    }
}
