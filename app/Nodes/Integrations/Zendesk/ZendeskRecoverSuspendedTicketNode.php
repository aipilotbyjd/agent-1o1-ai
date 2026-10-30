<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskRecoverSuspendedTicketNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_recover_suspended_ticket';
    }

    public function name(): string
    {
        return 'Zendesk: Recover Suspended Ticket';
    }

    public function description(): string
    {
        return 'Turns a suspended ticket into a normal ticket.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['suspended_ticket_id'],
            'properties' => [
                ...$this->credentialFields(),
                'suspended_ticket_id' => Field::text('Suspended ticket id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'suspended_ticket_id', 'The suspended ticket');

        return $this->shapeTicket((array) ($this->zendesk($run, $config, 'PUT', "/suspended_tickets/{$id}/recover")['ticket'] ?? []));
    }
}
