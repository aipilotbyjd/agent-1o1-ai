<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskListConversationsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_conversations';
    }

    public function name(): string
    {
        return 'Freshdesk: List Conversations';
    }

    public function description(): string
    {
        return 'Lists the replies and notes on a ticket.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');

        return $this->listing($run, $config, "/tickets/{$id}/conversations", 'conversations', [], $this->shapeConversation(...));
    }
}
