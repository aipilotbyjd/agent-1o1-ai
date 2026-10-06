<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SlackGetChannelHistoryNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_get_channel_history';
    }

    public function name(): string
    {
        return 'Slack: Get Channel History';
    }

    public function description(): string
    {
        return 'Fetches recent messages from a Slack channel.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel'],
            'properties' => [
                ...$this->credentialFields(),
                'channel' => Field::dynamic('Channel', 'slack.channels', 'The channel to use. Pick one, or enter a channel ID.', 'C0123456789'),
                'limit' => Field::integer('Limit', 'Maximum messages to return.', 100, 1, 1000),
                'oldest' => Field::advanced(Field::text('Oldest', 'Only messages after this Unix timestamp.', '1700000000')),
                'latest' => Field::advanced(Field::text('Latest', 'Only messages before this Unix timestamp.', '1700000000')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get($run, 'conversations.history', $config, array_filter([
            'channel' => $config['channel'],
            'limit' => $config['limit'] ?? null,
            'oldest' => $config['oldest'] ?? null,
            'latest' => $config['latest'] ?? null,
        ], fn ($value) => $value !== null));
    }
}
