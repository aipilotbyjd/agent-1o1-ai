<?php

namespace App\Nodes\Integrations\Gmail;

use App\Models\Runs\Run;

class GmailCreateDraftNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_create_draft';
    }

    public function name(): string
    {
        return 'Gmail: Create Draft';
    }

    public function description(): string
    {
        return 'Creates a draft email message.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $raw = base64_encode(
            "To: {$config['to']}\r\n".
            "Subject: {$config['subject']}\r\n\r\n".
            $config['body']
        );

        return $this->post('/users/me/drafts', $config, [
            'message' => ['raw' => strtr($raw, '+/', '-_')],
        ]);
    }
}
