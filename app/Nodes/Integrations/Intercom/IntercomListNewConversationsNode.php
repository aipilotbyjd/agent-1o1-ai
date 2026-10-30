<?php

namespace App\Nodes\Integrations\Intercom;

class IntercomListNewConversationsNode extends AbstractIntercomSearchTriggerNode
{
    public function type(): string
    {
        return 'intercom_list_new_conversations';
    }

    public function name(): string
    {
        return 'Intercom: List New Conversations';
    }

    public function description(): string
    {
        return 'Lists the conversations started lately. As a trigger it starts the flow for each one. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'conversations';
    }

    public function triggerName(): string
    {
        return 'Intercom: New conversation';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a conversation starts in Intercom. Checked every few minutes.';
    }
}
