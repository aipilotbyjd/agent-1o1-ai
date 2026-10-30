<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskReplyToTicketNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_reply_to_ticket';
    }

    public function name(): string
    {
        return 'Freshdesk: Reply To Ticket';
    }

    public function description(): string
    {
        return 'Sends a public reply on a ticket.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'body' => Field::textarea('Reply', 'HTML is kept.'),
                'from_email' => Field::advanced(Field::text('From address')),
                'user_id' => Field::advanced(Field::dynamic('As agent', 'freshdesk.agents')),
                'cc_emails' => Field::advanced(Field::emails('CC')),
                'bcc_emails' => Field::advanced(Field::emails('BCC')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');

        return $this->shapeConversation($this->freshdesk($run, $config, 'POST', "/tickets/{$id}/reply", [], $this->present(['body' => $this->requiredString($config, 'body', 'The reply'), 'from_email' => $this->text($config, 'from_email'), 'user_id' => $this->optionalId($config, 'user_id', 'The agent'), 'cc_emails' => $this->words($config, 'cc_emails'), 'bcc_emails' => $this->words($config, 'bcc_emails')])));
    }
}
