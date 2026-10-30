<?php

namespace App\Nodes\Integrations\Zendesk;

class ZendeskListUpdatedUsersNode extends AbstractZendeskSearchTriggerNode
{
    public function type(): string
    {
        return 'zendesk_list_updated_users';
    }

    public function name(): string
    {
        return 'Zendesk: List Updated Users';
    }

    public function description(): string
    {
        return 'Lists the people changed lately, newest first. As a trigger it starts the flow for each change. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'user';
    }

    protected function stamp(): string
    {
        return 'updated';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'users';
    }

    public function triggerName(): string
    {
        return 'Zendesk: Updated user';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a person changes in Zendesk. Checked every few minutes.';
    }
}
