<?php

namespace App\Nodes\Integrations\Apollo;

class ApolloListChangedContactsNode extends AbstractApolloRecordTriggerNode
{
    public function type(): string
    {
        return 'apollo_list_changed_contacts';
    }

    public function name(): string
    {
        return 'Apollo: List Changed Contacts';
    }

    public function description(): string
    {
        return 'Lists the contacts changed lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'contacts';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Apollo: Contact updated';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact is updated in Apollo. Checks every few minutes.';
    }
}
