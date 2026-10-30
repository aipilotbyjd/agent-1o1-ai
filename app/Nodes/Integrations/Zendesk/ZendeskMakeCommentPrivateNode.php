<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskMakeCommentPrivateNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_make_comment_private';
    }

    public function name(): string
    {
        return 'Zendesk: Make Comment Private';
    }

    public function description(): string
    {
        return 'Turns a public comment into an internal note.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'comment_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'comment_id' => Field::text('Comment id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $comment = $this->numericId($config, 'comment_id', 'The comment');
        $this->zendesk($run, $config, 'PUT', "/tickets/{$id}/comments/{$comment}/make_private");

        return ['private' => true, 'ticket_id' => (int) $id, 'comment_id' => (int) $comment];
    }
}
