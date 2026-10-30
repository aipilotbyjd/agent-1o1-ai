<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskGetTicketMetricsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_get_ticket_metrics';
    }

    public function name(): string
    {
        return 'Zendesk: Get Ticket Metrics';
    }

    public function description(): string
    {
        return 'Reads a ticket\'s response and resolution times, reopens and replies.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');

        return $this->findOne($run, $config, "/tickets/{$id}/metrics", 'ticket_metric', $id);
    }
}
