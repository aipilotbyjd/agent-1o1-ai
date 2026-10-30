<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskAttachFileToTicketNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_attach_file_to_ticket';
    }

    public function name(): string
    {
        return 'Zendesk: Attach File To Ticket';
    }

    public function description(): string
    {
        return 'Uploads a file from its address and adds it to a ticket with a comment.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'file_url', 'comment'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'file_url' => Field::text('File address', 'A public http(s) address.', 'https://example.com/report.pdf'),
                'filename' => Field::advanced(Field::text('File name', 'Taken from the address when empty.')),
                'comment' => Field::textarea('Comment'),
                'comment_public' => Field::advanced(Field::boolean('Public comment', 'Off makes it an internal note.', true)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $comment = $this->requiredString($config, 'comment', 'The comment');
        $upload = $this->sendUpload($run, $config);
        $this->zendesk($run, $config, 'PUT', "/tickets/{$id}", [], ['ticket' => ['comment' => [...$this->commentBody($config, $comment), 'uploads' => [$upload['token']]]]]);

        return ['attached' => true, 'ticket_id' => (int) $id, 'token' => $upload['token'], 'attachment' => $upload['attachment']];
    }
}
