<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskSetTicketStatusNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_set_ticket_status';
    }

    public function name(): string
    {
        return 'Freshdesk: Set Ticket Status';
    }

    public function description(): string
    {
        return 'Sets a ticket\'s status.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'status'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'status' => Field::select('Status', ['2' => 'Open', '3' => 'Pending', '4' => 'Resolved', '5' => 'Closed'], null, '2'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->changeTicket($run, $config, ['status' => (int) $this->numericId($config, 'status', 'The status')]);
    }
}
