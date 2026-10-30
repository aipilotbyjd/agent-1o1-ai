<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskUpdateTicketNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_update_ticket';
    }

    public function name(): string
    {
        return 'Zendesk: Update Ticket';
    }

    public function description(): string
    {
        return 'Changes a ticket\'s fields, adds a comment, or both. Only what is filled in changes.';
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
                'ticket_id' => Field::text('Ticket id', 'A ticket number from a previous step.'),
                ...$this->ticketFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $body = $this->ticketBody($config);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->shapeTicket((array) ($this->zendesk($run, $config, 'PUT', "/tickets/{$id}", [], ['ticket' => $body])['ticket'] ?? []));
    }
}
