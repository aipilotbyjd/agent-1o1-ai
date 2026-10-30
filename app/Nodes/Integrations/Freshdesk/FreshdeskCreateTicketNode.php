<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FreshdeskCreateTicketNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_create_ticket';
    }

    public function name(): string
    {
        return 'Freshdesk: Create Ticket';
    }

    public function description(): string
    {
        return 'Opens a ticket for a requester, found by their email or id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['subject', 'description'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->ticketFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->ticketBody($config);
        $this->requiredString($config, 'subject', 'The subject');
        $this->requiredString($config, 'description', 'The description');
        if (! isset($body['email']) && ! isset($body['requester_id']) && ! isset($body['phone'])) {
            throw new \InvalidArgumentException('Say who the ticket is from: an email, a requester id or a phone.');
        }

        return $this->shapeTicket($this->freshdesk($run, $config, 'POST', '/tickets', [], [...['status' => 2, 'priority' => 1], ...$body]));
    }
}
