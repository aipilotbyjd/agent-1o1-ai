<?php

namespace App\Nodes\Integrations\Intercom;

class IntercomListUpdatedTicketsNode extends AbstractIntercomSearchTriggerNode
{
    public function type(): string
    {
        return 'intercom_list_updated_tickets';
    }

    public function name(): string
    {
        return 'Intercom: List Updated Tickets';
    }

    public function description(): string
    {
        return 'Lists the tickets changed lately. As a trigger it starts the flow for each change. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'tickets';
    }

    protected function stampField(): ?string
    {
        return 'updated_at';
    }

    protected function eventStamp(array $item): ?string
    {
        return (string) ($item['updated_at'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Intercom: Updated ticket';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a ticket changes in Intercom. Checked every few minutes.';
    }
}
