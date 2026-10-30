<?php

namespace App\Nodes\Integrations\Apollo;

class ApolloListNewContactsNode extends AbstractApolloRecordTriggerNode
{
    public function type(): string
    {
        return 'apollo_list_new_contacts';
    }

    public function name(): string
    {
        return 'Apollo: List New Contacts';
    }

    public function description(): string
    {
        return 'Lists the contacts created lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'contacts';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Apollo: New contact';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact is created in Apollo. Checks every few minutes.';
    }
}
