<?php

namespace App\Nodes\Integrations\Intercom;

class IntercomListAdminRepliesNode extends AbstractIntercomSearchTriggerNode
{
    public function type(): string
    {
        return 'intercom_list_admin_replies';
    }

    public function name(): string
    {
        return 'Intercom: List Teammate Replies';
    }

    public function description(): string
    {
        return 'Lists the conversations a teammate replied to lately. As a trigger it starts the flow for each reply. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'conversations';
    }

    protected function stampField(): ?string
    {
        return 'statistics.last_admin_reply_at';
    }

    protected function eventStamp(array $item): ?string
    {
        return (string) ($item['last_admin_reply_at'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Intercom: New reply from a teammate';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a teammate replies in a conversation. Checked every few minutes.';
    }
}
