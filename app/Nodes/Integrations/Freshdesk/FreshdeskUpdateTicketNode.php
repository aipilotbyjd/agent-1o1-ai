<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskUpdateTicketNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_update_ticket';
    }

    public function name(): string
    {
        return 'Freshdesk: Update Ticket';
    }

    public function description(): string
    {
        return 'Changes a ticket. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                ...$this->ticketFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->ticketBody($config);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->changeTicket($run, $config, $body);
    }
}
