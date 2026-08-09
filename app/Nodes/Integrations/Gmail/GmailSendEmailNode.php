<?php

namespace App\Nodes\Integrations\Gmail;

use App\Models\Runs\Run;

class GmailSendEmailNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_send_email';
    }

    public function name(): string
    {
        return 'Gmail: Send Email';
    }

    public function description(): string
    {
        return 'Sends an email via Gmail.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['access_token', 'to', 'subject', 'body'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'to' => ['type' => 'string'],
                'subject' => ['type' => 'string'],
                'body' => ['type' => 'string'],
                'is_html' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $contentType = ($config['is_html'] ?? false) ? 'text/html' : 'text/plain';

        $raw = base64_encode(
            "To: {$config['to']}\r\n".
            "Subject: {$config['subject']}\r\n".
            "Content-Type: {$contentType}; charset=utf-8\r\n\r\n".
            $config['body']
        );

        return $this->post('/users/me/messages/send', $config, [
            'raw' => strtr($raw, '+/', '-_'),
        ]);
    }
}
