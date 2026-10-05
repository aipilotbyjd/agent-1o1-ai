<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GmailReplyToMessageNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_reply_to_message';
    }

    public function name(): string
    {
        return 'Gmail: Reply to Message';
    }

    public function description(): string
    {
        return 'Replies to an existing email thread.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['to', 'message_id', 'thread_id', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'message_id' => Field::dynamic('Message', 'gmail.messages', 'Pick a recent email, or map a message ID from an earlier step.', '18c2f0a1b2c3d4e5'),
                'thread_id' => Field::dynamic('Thread', 'gmail.threads', 'The conversation the reply belongs to.'),
                'to' => Field::emails('To'),
                'subject' => Field::text('Subject', 'Sent as "Re: <subject>".'),
                'body' => Field::textarea('Body'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $subject = $config['subject'] ?? '';

        $raw = base64_encode(
            "To: {$config['to']}\r\n".
            "Subject: Re: {$subject}\r\n".
            "In-Reply-To: {$config['message_id']}\r\n".
            "References: {$config['message_id']}\r\n".
            "Content-Type: text/plain; charset=utf-8\r\n\r\n".
            $config['body']
        );

        return $this->post($run, '/users/me/messages/send', $config, [
            'raw' => strtr($raw, '+/', '-_'),
            'threadId' => $config['thread_id'],
        ]);
    }
}
