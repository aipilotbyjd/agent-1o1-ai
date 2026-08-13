<?php

namespace App\Nodes\Integrations\Gmail;

use App\Models\Runs\Run;

class GmailAddLabelNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_add_label';
    }

    public function name(): string
    {
        return 'Gmail: Add Label';
    }

    public function description(): string
    {
        return 'Adds labels to a message.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id', 'label_ids'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'integer'],
                'message_id' => ['type' => 'string'],
                'label_ids' => ['type' => 'array'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, "/users/me/messages/{$config['message_id']}/modify", $config, [
            'addLabelIds' => (array) $config['label_ids'],
            'removeLabelIds' => [],
        ]);
    }
}
