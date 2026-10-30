<?php

namespace App\Nodes\Integrations\Freshdesk;

class FreshdeskListNewTicketsNode extends AbstractFreshdeskChangeTriggerNode
{
    public function type(): string
    {
        return 'freshdesk_list_new_tickets';
    }

    public function name(): string
    {
        return 'Freshdesk: List New Tickets';
    }

    public function description(): string
    {
        return 'Lists the tickets opened lately, newest first. As a trigger it starts the flow for each one. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'tickets';
    }

    protected function onlyNew(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Freshdesk: New ticket';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a ticket is opened in Freshdesk. Checked every few minutes.';
    }
}
