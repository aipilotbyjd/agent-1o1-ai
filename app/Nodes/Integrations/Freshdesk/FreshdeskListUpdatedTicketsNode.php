<?php

namespace App\Nodes\Integrations\Freshdesk;

class FreshdeskListUpdatedTicketsNode extends AbstractFreshdeskChangeTriggerNode
{
    public function type(): string
    {
        return 'freshdesk_list_updated_tickets';
    }

    public function name(): string
    {
        return 'Freshdesk: List Updated Tickets';
    }

    public function description(): string
    {
        return 'Lists the tickets changed lately, newest first. As a trigger it starts the flow for each change. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'tickets';
    }

    public function triggerName(): string
    {
        return 'Freshdesk: Updated ticket';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a ticket changes in Freshdesk. Checked every few minutes.';
    }
}
