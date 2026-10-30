<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskReplySideConversationNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_reply_side_conversation';
    }

    public function name(): string
    {
        return 'Zendesk: Reply to Side Conversation';
    }

    public function description(): string
    {
        return 'Sends a reply in a side conversation.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'side_conversation_id', 'to', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'side_conversation_id' => Field::text('Side conversation id'),
                'to' => Field::emails('To'),
                'subject' => Field::advanced(Field::text('Subject')),
                'body' => Field::textarea('Message'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $side = $this->safeId($config, 'side_conversation_id', 'The side conversation');
        $result = $this->zendesk($run, $config, 'POST', "/tickets/{$id}/side_conversations/{$side}/reply", [], ['message' => $this->sideMessage($config)]);

        return (array) ($result['side_conversation'] ?? $result);
    }
}
