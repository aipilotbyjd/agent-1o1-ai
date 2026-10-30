<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskBatchCreateTicketsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_batch_create_tickets';
    }

    public function name(): string
    {
        return 'Zendesk: Batch Create Tickets';
    }

    public function description(): string
    {
        return 'Creates up to 100 tickets at once from a JSON list. Answers with a job to check.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['tickets'],
            'properties' => [
                ...$this->credentialFields(),
                'tickets' => Field::json('Tickets (JSON)', 'A list of Zendesk ticket objects, e.g. [{"subject": "Setup", "comment": {"body": "Hello"}}].', 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $tickets = is_string($config['tickets'] ?? null) ? json_decode((string) $config['tickets'], true) : ($config['tickets'] ?? null);
        if (! is_array($tickets) || $tickets === [] || ! array_is_list($tickets) || count($tickets) > 100) {
            throw new \InvalidArgumentException('The tickets must be a JSON list of 1 to 100 ticket objects.');
        }

        return ['job_status' => $this->zendesk($run, $config, 'POST', '/tickets/create_many', [], ['tickets' => $tickets])['job_status'] ?? null];
    }
}
