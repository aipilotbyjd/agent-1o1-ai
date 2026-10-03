<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class OutlookReplyToMessageNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_reply_to_message';
    }

    public function name(): string
    {
        return 'Outlook: Reply to Message';
    }

    public function description(): string
    {
        return 'Replies to a message in its thread (reply-all when asked).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id', 'body'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'string'],
                'message_id' => ['type' => 'string'],
                'body' => ['type' => 'string'],
                'reply_all' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $action = ($config['reply_all'] ?? false) ? 'replyAll' : 'reply';

        return $this->post($run, '/me/messages/'.rawurlencode((string) $config['message_id'])."/{$action}", $config, [
            'comment' => $config['body'],
        ]);
    }
}
