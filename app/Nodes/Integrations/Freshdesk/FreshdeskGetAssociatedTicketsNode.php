<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetAssociatedTicketsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_associated_tickets';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Associated Tickets';
    }

    public function description(): string
    {
        return 'Lists the tickets linked to a ticket.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $rows = $this->freshdesk($run, $config, 'GET', "/tickets/{$id}/associated_tickets");

        return ['tickets' => array_map(fn (mixed $ticket): array => $this->shapeTicket((array) $ticket), array_values((array) ($rows['tickets'] ?? $rows))), 'count' => count((array) ($rows['tickets'] ?? $rows))];
    }
}
