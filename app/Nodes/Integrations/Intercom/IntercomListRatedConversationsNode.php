<?php

namespace App\Nodes\Integrations\Intercom;

class IntercomListRatedConversationsNode extends AbstractIntercomSearchTriggerNode
{
    public function type(): string
    {
        return 'intercom_list_rated_conversations';
    }

    public function name(): string
    {
        return 'Intercom: List Rated Conversations';
    }

    public function description(): string
    {
        return 'Lists the conversations rated by a contact lately. As a trigger it starts the flow for each rating. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'conversations';
    }

    protected function stampField(): ?string
    {
        return 'conversation_rating.replied_at';
    }

    public function triggerName(): string
    {
        return 'Intercom: New conversation rating';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact rates a conversation. Checked every few minutes.';
    }
}
