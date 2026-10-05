<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

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

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['to', 'subject', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'to' => Field::emails('To'),
                'subject' => Field::text('Subject', null, 'Weekly report'),
                'body' => Field::textarea('Body'),
                'is_html' => Field::boolean('Send as HTML', 'Treat the body as HTML instead of plain text.'),
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

        return $this->post($run, '/users/me/messages/send', $config, [
            'raw' => strtr($raw, '+/', '-_'),
        ]);
    }
}
