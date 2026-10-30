<?php

namespace App\Nodes\Integrations\Zendesk;

class ZendeskListNewUsersNode extends AbstractZendeskSearchTriggerNode
{
    public function type(): string
    {
        return 'zendesk_list_new_users';
    }

    public function name(): string
    {
        return 'Zendesk: List New Users';
    }

    public function description(): string
    {
        return 'Lists the people added lately, newest first. As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function resource(): string
    {
        return 'user';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'users';
    }

    public function triggerName(): string
    {
        return 'Zendesk: New user';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a person is added in Zendesk. Instant, with a check every few minutes.';
    }
}
