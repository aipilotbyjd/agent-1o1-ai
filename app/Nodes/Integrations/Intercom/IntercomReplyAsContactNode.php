<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomReplyAsContactNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_reply_as_contact';
    }

    public function name(): string
    {
        return 'Intercom: Reply As Contact';
    }

    public function description(): string
    {
        return 'Adds a reply to a conversation on behalf of the contact.';
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
                'contact_id' => Field::text('Contact id', 'The contact\'s Intercom id.'),
                'email' => Field::advanced(Field::text('Or email')),
                'body' => Field::textarea('Message'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'conversation_id', 'The conversation');
        $who = $this->present(['intercom_user_id' => $this->text($config, 'contact_id'), 'email' => filled($config['email'] ?? null) ? $this->email($config) : null]);
        if ($who === []) {
            throw new \InvalidArgumentException('Say which contact is replying.');
        }

        return $this->shapeConversation($this->intercom($run, $config, 'POST', "/conversations/{$id}/reply", [], ['message_type' => 'comment', 'type' => 'user', 'body' => $this->requiredString($config, 'body', 'The message'), ...$who]));
    }
}
