<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskCloseTicketNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_close_ticket';
    }

    public function name(): string
    {
        return 'Freshdesk: Close Ticket';
    }

    public function description(): string
    {
        return 'Closes a ticket.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
        return $this->changeTicket($run, $config, ['status' => 5]);
    }
}
