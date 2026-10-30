<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskRestoreTicketNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_restore_ticket';
    }

    public function name(): string
    {
        return 'Zendesk: Restore Ticket';
    }

    public function description(): string
    {
        return 'Brings back a ticket that was deleted.';
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
        $this->zendesk($run, $config, 'PUT', "/deleted_tickets/{$id}/restore");

        return ['restored' => true, 'id' => (int) $id];
    }
}
