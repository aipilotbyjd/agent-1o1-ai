<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ZendeskCreateTicketNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_create_ticket';
    }

    public function name(): string
    {
        return 'Zendesk: Create Ticket';
    }

    public function description(): string
    {
        return 'Opens a ticket for a requester, with a first comment.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['comment'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->ticketFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->ticketBody($config);
        $this->requiredString($config, 'comment', 'The first comment');

        return $this->shapeTicket((array) ($this->zendesk($run, $config, 'POST', '/tickets', [], ['ticket' => $body])['ticket'] ?? []));
    }
}
