<?php

namespace App\Nodes\Integrations\Intercom;

class IntercomListContactRepliesNode extends AbstractIntercomSearchTriggerNode
{
    public function type(): string
    {
        return 'intercom_list_contact_replies';
    }

    public function name(): string
    {
        return 'Intercom: List Contact Replies';
    }

    public function description(): string
    {
        return 'Lists the conversations a contact replied to lately. As a trigger it starts the flow for each reply. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'conversations';
    }

    protected function stampField(): ?string
    {
        return 'statistics.last_contact_reply_at';
    }

    protected function eventStamp(array $item): ?string
    {
        return (string) ($item['last_contact_reply_at'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Intercom: Contact replied';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact replies in a conversation. Checked every few minutes.';
    }
}
