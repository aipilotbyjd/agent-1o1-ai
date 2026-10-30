<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskSetTicketPriorityNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_set_ticket_priority';
    }

    public function name(): string
    {
        return 'Freshdesk: Set Ticket Priority';
    }

    public function description(): string
    {
        return 'Sets a ticket\'s priority.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'priority'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'priority' => Field::select('Priority', ['1' => 'Low', '2' => 'Medium', '3' => 'High', '4' => 'Urgent'], null, '1'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->changeTicket($run, $config, ['priority' => (int) $this->numericId($config, 'priority', 'The priority')]);
    }
}
