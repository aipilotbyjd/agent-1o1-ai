<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskBulkDeleteTicketsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_bulk_delete_tickets';
    }

    public function name(): string
    {
        return 'Freshdesk: Delete Multiple Tickets';
    }

    public function description(): string
    {
        return 'Moves many tickets to the trash in the background. Answers with a job to check.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_ids' => Field::text('Ticket ids', 'Separated by commas.', '101, 102'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->freshdesk($run, $config, 'POST', '/tickets/bulk_delete', [], ['bulk_action' => ['ids' => $this->idList($config, 'ticket_ids', 'The tickets')]]);
    }
}
