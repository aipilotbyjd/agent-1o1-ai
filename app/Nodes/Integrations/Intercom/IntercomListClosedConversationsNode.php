<?php

namespace App\Nodes\Integrations\Intercom;

class IntercomListClosedConversationsNode extends AbstractIntercomSearchTriggerNode
{
    public function type(): string
    {
        return 'intercom_list_closed_conversations';
    }

    public function name(): string
    {
        return 'Intercom: List Closed Conversations';
    }

    public function description(): string
    {
        return 'Lists the conversations closed lately. As a trigger it starts the flow for each closing. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'conversations';
    }

    protected function stampField(): ?string
    {
        return 'statistics.last_close_at';
    }

    protected function filters(array $config): array
    {
        return [['field' => 'state', 'operator' => '=', 'value' => 'closed']];
    }

    protected function eventStamp(array $item): ?string
    {
        return (string) ($item['last_close_at'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Intercom: New closed conversation';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a conversation is closed. Checked every few minutes.';
    }
}
