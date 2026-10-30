<?php

namespace App\Nodes\Integrations\Intercom;

class IntercomListUpdatedContactsNode extends AbstractIntercomSearchTriggerNode
{
    public function type(): string
    {
        return 'intercom_list_updated_contacts';
    }

    public function name(): string
    {
        return 'Intercom: List Updated Contacts';
    }

    public function description(): string
    {
        return 'Lists the contacts changed lately. As a trigger it starts the flow for each change. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'contacts';
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
        return 'Intercom: Updated contact';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact changes in Intercom. Checked every few minutes.';
    }
}
