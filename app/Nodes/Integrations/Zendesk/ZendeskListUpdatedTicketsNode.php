<?php

namespace App\Nodes\Integrations\Zendesk;

class ZendeskListUpdatedTicketsNode extends AbstractZendeskSearchTriggerNode
{
    public function type(): string
    {
        return 'zendesk_list_updated_tickets';
    }

    public function name(): string
    {
        return 'Zendesk: List Updated Tickets';
    }

    public function description(): string
    {
        return 'Lists the tickets changed lately, newest first. As a trigger it starts the flow for each change. Instant, with a check every few minutes.';
    }

    protected function resource(): string
    {
        return 'ticket';
    }

    protected function stamp(): string
    {
        return 'updated';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'tickets';
    }

    public function triggerName(): string
    {
        return 'Zendesk: Updated ticket';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a ticket changes in Zendesk. Instant, with a check every few minutes.';
    }
}
