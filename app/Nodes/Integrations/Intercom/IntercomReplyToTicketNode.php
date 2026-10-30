<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomReplyToTicketNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_reply_to_ticket';
    }

    public function name(): string
    {
        return 'Intercom: Add Comment To Ticket';
    }

    public function description(): string
    {
        return 'Replies on a ticket as a teammate, or leaves an internal note.';
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
                'message_type' => Field::select('Reply type', ['comment' => 'Reply', 'note' => 'Internal note'], null, 'comment'),
                'body' => Field::textarea('Message'),
                'admin_id' => Field::advanced(Field::dynamic('As teammate', 'intercom.admins')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'ticket_id', 'The ticket');

        return $this->intercom($run, $config, 'POST', "/tickets/{$id}/reply", [], ['message_type' => ($config['message_type'] ?? 'comment') === 'note' ? 'note' : 'comment', 'type' => 'admin', 'admin_id' => $this->adminId($run, $config), 'body' => $this->requiredString($config, 'body', 'The message')]);
    }
}
