<?php

namespace App\Nodes\Integrations\Slack;

use App\Models\Runs\Run;

class SlackPostMessageNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_post_message';
    }

    public function name(): string
    {
        return 'Slack: Post Message';
    }

    public function description(): string
    {
        return 'Posts a message to a Slack channel, optionally as a thread reply.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['access_token', 'channel', 'text'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'channel' => ['type' => 'string'],
                'text' => ['type' => 'string'],
                'thread_ts' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post('chat.postMessage', $config, array_filter([
            'channel' => $config['channel'],
            'text' => $config['text'],
            'thread_ts' => $config['thread_ts'] ?? null,
        ], fn ($value) => $value !== null));
    }
}
