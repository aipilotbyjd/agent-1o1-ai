<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskGetSideConversationNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_get_side_conversation';
    }

    public function name(): string
    {
        return 'Zendesk: Get Side Conversation';
    }

    public function description(): string
    {
        return 'Reads one side conversation of a ticket.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'side_conversation_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'side_conversation_id' => Field::text('Side conversation id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $side = $this->safeId($config, 'side_conversation_id', 'The side conversation');

        return $this->findOne($run, $config, "/tickets/{$id}/side_conversations/{$side}", 'side_conversation', $side);
    }
}
