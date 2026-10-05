<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

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

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel', 'text'],
            'properties' => [
                ...$this->credentialFields(),
                'channel' => Field::dynamic('Channel', 'slack.channels', 'The channel to use. Pick one, or enter a channel ID.', 'C0123456789'),
                'text' => Field::textarea('Message', 'Supports Slack mrkdwn and {{templates}}.', 'Hello from my workflow!'),
                'thread_ts' => Field::advanced(Field::text('Reply in thread', 'The parent message timestamp (ts) to reply under.', '1700000000.000100')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, 'chat.postMessage', $config, array_filter([
            'channel' => $config['channel'],
            'text' => $config['text'],
            'thread_ts' => $config['thread_ts'] ?? null,
        ], fn ($value) => $value !== null));
    }
}
