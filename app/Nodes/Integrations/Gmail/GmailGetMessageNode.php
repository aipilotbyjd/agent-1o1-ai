<?php

namespace App\Nodes\Integrations\Gmail;

use App\Models\Runs\Run;

class GmailGetMessageNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_get_message';
    }

    public function name(): string
    {
        return 'Gmail: Get Message';
    }

    public function description(): string
    {
        return 'Retrieves a specific email message by ID.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'integer'],
                'message_id' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get($run, "/users/me/messages/{$config['message_id']}", $config);
    }
}
