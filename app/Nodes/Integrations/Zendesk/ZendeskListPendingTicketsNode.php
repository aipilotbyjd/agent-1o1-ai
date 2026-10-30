<?php

namespace App\Nodes\Integrations\Zendesk;

class ZendeskListPendingTicketsNode extends AbstractZendeskSearchTriggerNode
{
    public function type(): string
    {
        return 'zendesk_list_pending_tickets';
    }

    public function name(): string
    {
        return 'Zendesk: List Pending Tickets';
    }

    public function description(): string
    {
        return 'Lists tickets set to pending lately. As a trigger it starts the flow when a ticket goes pending. Instant, with a check every few minutes.';
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
        return 'status:pending';
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
        return 'Zendesk: Ticket pending';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow once when a ticket goes to pending. Instant, with a check every few minutes.';
    }
}
