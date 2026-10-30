<?php

namespace App\Nodes\Integrations\Zendesk;

class ZendeskListNewTicketsNode extends AbstractZendeskSearchTriggerNode
{
    public function type(): string
    {
        return 'zendesk_list_new_tickets';
    }

    public function name(): string
    {
        return 'Zendesk: List New Tickets';
    }

    public function description(): string
    {
        return 'Lists the tickets opened lately, newest first. As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function resource(): string
    {
        return 'ticket';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'tickets';
    }

    public function triggerName(): string
    {
        return 'Zendesk: New ticket';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a ticket is opened in Zendesk. Instant, with a check every few minutes.';
    }
}
