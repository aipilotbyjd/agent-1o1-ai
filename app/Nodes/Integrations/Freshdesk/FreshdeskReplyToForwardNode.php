<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskReplyToForwardNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_reply_to_forward';
    }

    public function name(): string
    {
        return 'Freshdesk: Reply To Forward';
    }

    public function description(): string
    {
        return 'Replies to a forwarded ticket\'s thread by email.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'to_emails', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'to_emails' => Field::emails('To'),
                'body' => Field::textarea('Message', 'HTML is kept.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');

        return $this->shapeConversation($this->freshdesk($run, $config, 'POST', "/tickets/{$id}/reply_to_forward", [], ['body' => $this->requiredString($config, 'body', 'The message'), 'to_emails' => $this->words($config, 'to_emails')]));
    }
}
