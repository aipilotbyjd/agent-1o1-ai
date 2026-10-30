<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskCreateSideConversationNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_create_side_conversation';
    }

    public function name(): string
    {
        return 'Zendesk: Create Side Conversation';
    }

    public function description(): string
    {
        return 'Starts a side conversation by email on a ticket.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'to', 'subject', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'to' => Field::emails('To'),
                'subject' => Field::text('Subject'),
                'body' => Field::textarea('Message'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $result = $this->zendesk($run, $config, 'POST', "/tickets/{$id}/side_conversations", [], ['message' => $this->sideMessage($config)]);

        return (array) ($result['side_conversation'] ?? $result);
    }
}
