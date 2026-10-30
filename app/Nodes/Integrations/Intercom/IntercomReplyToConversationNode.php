<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomReplyToConversationNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_reply_to_conversation';
    }

    public function name(): string
    {
        return 'Intercom: Reply To Conversation';
    }

    public function description(): string
    {
        return 'Replies to a conversation as a teammate, or leaves an internal note.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['conversation_id', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'conversation_id' => Field::text('Conversation id'),
                'message_type' => Field::select('Reply type', ['comment' => 'Reply', 'note' => 'Internal note'], null, 'comment'),
                'body' => Field::textarea('Message'),
                'admin_id' => Field::advanced(Field::dynamic('As teammate', 'intercom.admins')),
                'attachment_urls' => Field::advanced(Field::text('Attachment addresses', 'Separated by commas, at most 10.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'conversation_id', 'The conversation');
        $attachments = array_values(array_filter(array_map('trim', explode(',', (string) ($config['attachment_urls'] ?? '')))));

        return $this->shapeConversation($this->intercom($run, $config, 'POST', "/conversations/{$id}/reply", [], $this->present(['message_type' => ($config['message_type'] ?? 'comment') === 'note' ? 'note' : 'comment', 'type' => 'admin', 'admin_id' => $this->adminId($run, $config), 'body' => $this->requiredString($config, 'body', 'The message'), 'attachment_urls' => $attachments])));
    }
}
