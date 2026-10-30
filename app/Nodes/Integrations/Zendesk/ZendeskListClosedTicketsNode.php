<?php

namespace App\Nodes\Integrations\Zendesk;

class ZendeskListClosedTicketsNode extends AbstractZendeskSearchTriggerNode
{
    public function type(): string
    {
        return 'zendesk_list_closed_tickets';
    }

    public function name(): string
    {
        return 'Zendesk: List Closed Tickets';
    }

    public function description(): string
    {
        return 'Lists tickets closed lately. As a trigger it starts the flow once for each ticket. Instant, with a check every few minutes.';
    }

    protected function resource(): string
    {
        return 'ticket';
    }

    protected function stamp(): string
    {
        return 'updated';
    }

    protected function searchFilter(array $config): string
    {
        return 'status:closed';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['id'] ?? '');
    }

    protected function triggerItemsPath(): ?string
    {
        return 'tickets';
    }

    public function triggerName(): string
    {
        return 'Zendesk: Ticket closed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow once when a ticket is closed. Instant, with a check every few minutes.';
    }
}
