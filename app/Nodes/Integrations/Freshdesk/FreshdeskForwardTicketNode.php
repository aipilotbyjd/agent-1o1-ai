<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskForwardTicketNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_forward_ticket';
    }

    public function name(): string
    {
        return 'Freshdesk: Forward Ticket';
    }

    public function description(): string
    {
        return 'Forwards a ticket by email.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'to_emails'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'to_emails' => Field::emails('To'),
                'body' => Field::advanced(Field::textarea('Message', 'HTML is kept.')),
                'cc_emails' => Field::advanced(Field::emails('CC')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');

        return $this->shapeConversation($this->freshdesk($run, $config, 'POST', "/tickets/{$id}/forward", [], $this->present(['body' => $this->text($config, 'body'), 'to_emails' => $this->words($config, 'to_emails'), 'cc_emails' => $this->words($config, 'cc_emails')])));
    }
}
