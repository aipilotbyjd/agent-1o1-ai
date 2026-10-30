<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskBatchUpdateTicketsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_batch_update_tickets';
    }

    public function name(): string
    {
        return 'Zendesk: Batch Update Tickets';
    }

    public function description(): string
    {
        return 'Makes the same change to up to 100 tickets. Answers with a job to check.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_ids' => Field::text('Ticket ids', 'Ticket numbers separated by commas.', '101, 102, 103'),
                ...$this->ticketFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $ids = $this->idList($config, 'ticket_ids', 'The tickets');
        $body = $this->ticketBody($config);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return ['job_status' => $this->zendesk($run, $config, 'PUT', '/tickets/update_many', ['ids' => implode(',', $ids)], ['ticket' => $body])['job_status'] ?? null];
    }
}
