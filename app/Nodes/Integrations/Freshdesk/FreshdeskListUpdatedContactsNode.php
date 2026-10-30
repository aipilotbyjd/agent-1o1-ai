<?php

namespace App\Nodes\Integrations\Freshdesk;

class FreshdeskListUpdatedContactsNode extends AbstractFreshdeskChangeTriggerNode
{
    public function type(): string
    {
        return 'freshdesk_list_updated_contacts';
    }

    public function name(): string
    {
        return 'Freshdesk: List Updated Contacts';
    }

    public function description(): string
    {
        return 'Lists the contacts changed lately. As a trigger it starts the flow for each change. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'contacts';
    }

    public function triggerName(): string
    {
        return 'Freshdesk: Updated contact';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact changes in Freshdesk. Checked every few minutes.';
    }
}
