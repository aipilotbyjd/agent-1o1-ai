<?php

namespace App\Nodes\Integrations\Gmail;

use App\Models\Runs\Run;

class GmailDeleteMessageNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_delete_message';
    }

    public function name(): string
    {
        return 'Gmail: Delete Message';
    }

    public function description(): string
    {
        return 'Moves a message to trash.';
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

    public function outputSchema(array $config = []): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'trashed' => ['type' => 'boolean'],
                'message_id' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->post($run, "/users/me/messages/{$config['message_id']}/trash", $config);

        return ['trashed' => true, 'message_id' => $config['message_id']];
    }
}
