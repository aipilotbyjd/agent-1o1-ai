<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskDeleteTicketNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_delete_ticket';
    }

    public function name(): string
    {
        return 'Zendesk: Delete Ticket';
    }

    public function description(): string
    {
        return 'Moves a ticket to deleted tickets. It can be restored for 30 days.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $this->zendesk($run, $config, 'DELETE', "/tickets/{$id}");

        return ['deleted' => true, 'id' => (int) $id];
    }
}
