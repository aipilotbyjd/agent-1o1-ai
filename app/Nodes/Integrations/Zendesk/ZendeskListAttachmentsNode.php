<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskListAttachmentsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_list_attachments';
    }

    public function name(): string
    {
        return 'Zendesk: List Ticket Attachments';
    }

    public function description(): string
    {
        return 'Lists the attachments on all comments of a ticket.';
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
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $comments = $this->listAll($run, $config, "/tickets/{$id}/comments", 'comments', [], 1000);
        $attachments = [];
        foreach ($comments as $comment) {
            foreach ((array) ($comment['attachments'] ?? []) as $attachment) {
                $attachments[] = [...(array) $attachment, 'comment_id' => $comment['id'] ?? null];
            }
        }
        $attachments = array_slice($attachments, 0, $this->limit($config, 100));

        return ['attachments' => $attachments, 'count' => count($attachments)];
    }
}
