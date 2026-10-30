<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetTicketNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_ticket';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Ticket';
    }

    public function description(): string
    {
        return 'Reads a ticket by id, or says it was not found.';
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
                'include' => Field::advanced(Field::text('Also include', 'Any of conversations, requester, company, stats, separated by commas. Each one costs extra API credits.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');

        return $this->findOne($run, $config, "/tickets/{$id}", $id, fn (array $ticket): array => [...$this->shapeTicket($ticket), ...array_intersect_key($ticket, array_flip(['conversations', 'requester', 'company', 'stats']))], ['include' => $this->text($config, 'include')]);
    }
}
