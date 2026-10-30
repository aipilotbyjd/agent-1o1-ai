<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskAssignTicketToAgentNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_assign_ticket_to_agent';
    }

    public function name(): string
    {
        return 'Freshdesk: Assign Ticket To Agent';
    }

    public function description(): string
    {
        return 'Gives a ticket to an agent.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'responder_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'responder_id' => Field::dynamic('Agent', 'freshdesk.agents'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->changeTicket($run, $config, ['responder_id' => (int) $this->numericId($config, 'responder_id', 'The agent')]);
    }
}
