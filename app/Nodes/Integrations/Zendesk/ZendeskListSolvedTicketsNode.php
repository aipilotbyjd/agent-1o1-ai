<?php

namespace App\Nodes\Integrations\Zendesk;

class ZendeskListSolvedTicketsNode extends AbstractZendeskSearchTriggerNode
{
    public function type(): string
    {
        return 'zendesk_list_solved_tickets';
    }

    public function name(): string
    {
        return 'Zendesk: List Solved Tickets';
    }

    public function description(): string
    {
        return 'Lists tickets solved lately. As a trigger it starts the flow once for each ticket. Instant, with a check every few minutes.';
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
        return 'status:solved';
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
        return 'Zendesk: Ticket solved';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow once when a ticket is solved. Instant, with a check every few minutes.';
    }
}
