<?php

namespace App\Nodes\Integrations\Gmail;

use App\Models\Runs\Run;

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

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['to', 'message_id', 'thread_id', 'body'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'integer'],
                'to' => ['type' => 'string'],
                'message_id' => ['type' => 'string'],
                'thread_id' => ['type' => 'string'],
                'subject' => ['type' => 'string'],
                'body' => ['type' => 'string'],
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
