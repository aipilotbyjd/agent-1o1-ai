<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskRestoreTicketNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_restore_ticket';
    }

    public function name(): string
    {
        return 'Freshdesk: Restore Ticket';
    }

    public function description(): string
    {
        return 'Brings back a ticket from the trash.';
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
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $this->freshdesk($run, $config, 'PUT', "/tickets/{$id}/restore");

        return ['restored' => true, 'id' => (int) $id];
    }
}
