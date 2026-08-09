<?php

namespace App\Nodes\Integrations\Gmail;

use App\Models\Runs\Run;

class GmailModifyMessageNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_modify_message';
    }

    public function name(): string
    {
        return 'Gmail: Modify Message';
    }

    public function description(): string
    {
        return 'Adds or removes labels on a message.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['access_token', 'message_id'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'message_id' => ['type' => 'string'],
                'add_label_ids' => ['type' => 'array'],
                'remove_label_ids' => ['type' => 'array'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post("/users/me/messages/{$config['message_id']}/modify", $config, [
            'addLabelIds' => $config['add_label_ids'] ?? [],
            'removeLabelIds' => $config['remove_label_ids'] ?? [],
        ]);
    }
}
