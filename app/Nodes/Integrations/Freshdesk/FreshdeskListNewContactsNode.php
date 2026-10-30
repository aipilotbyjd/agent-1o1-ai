<?php

namespace App\Nodes\Integrations\Freshdesk;

class FreshdeskListNewContactsNode extends AbstractFreshdeskChangeTriggerNode
{
    public function type(): string
    {
        return 'freshdesk_list_new_contacts';
    }

    public function name(): string
    {
        return 'Freshdesk: List New Contacts';
    }

    public function description(): string
    {
        return 'Lists the contacts added lately. As a trigger it starts the flow for each one. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'contacts';
    }

    protected function onlyNew(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Freshdesk: New contact';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact is added in Freshdesk. Checked every few minutes.';
    }
}
