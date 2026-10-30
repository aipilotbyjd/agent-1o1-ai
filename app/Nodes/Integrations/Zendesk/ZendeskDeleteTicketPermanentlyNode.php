<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskDeleteTicketPermanentlyNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_delete_ticket_permanently';
    }

    public function name(): string
    {
        return 'Zendesk: Delete Ticket Permanently';
    }

    public function description(): string
    {
        return 'Erases a ticket that was already deleted. This cannot be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $job = $this->zendesk($run, $config, 'DELETE', "/deleted_tickets/{$id}");

        return ['deleted' => true, 'id' => (int) $id, 'job_status' => $job['job_status'] ?? null];
    }
}
