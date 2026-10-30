<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskAddCommentNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_add_comment';
    }

    public function name(): string
    {
        return 'Zendesk: Add Comment';
    }

    public function description(): string
    {
        return 'Adds a public reply or an internal note to a ticket.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'comment'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'comment' => Field::textarea('Comment', 'HTML is kept.'),
                'comment_public' => Field::advanced(Field::boolean('Public comment', 'Off makes it an internal note.', true)),
                'author_id' => Field::advanced(Field::dynamic('Author', 'zendesk.agents')),
                'uploads' => Field::advanced(Field::text('Upload tokens', 'From Upload Attachment, separated by commas.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $ticket = $this->zendesk($run, $config, 'PUT', "/tickets/{$id}", [], ['ticket' => ['comment' => $this->commentBody($config, $this->requiredString($config, 'comment', 'The comment'))]]);

        return ['added' => true, 'ticket_id' => (int) $id, 'audit_id' => data_get($ticket, 'audit.id'), 'status' => data_get($ticket, 'ticket.status')];
    }
}
