<?php

namespace App\Nodes\Integrations\Intercom;

class IntercomListNewTicketsNode extends AbstractIntercomSearchTriggerNode
{
    public function type(): string
    {
        return 'intercom_list_new_tickets';
    }

    public function name(): string
    {
        return 'Intercom: List New Tickets';
    }

    public function description(): string
    {
        return 'Lists the tickets opened lately. As a trigger it starts the flow for each one. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'tickets';
    }

    public function triggerName(): string
    {
        return 'Intercom: New ticket';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a ticket is opened in Intercom. Checked every few minutes.';
    }
}
