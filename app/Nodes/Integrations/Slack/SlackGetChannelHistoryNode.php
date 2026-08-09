<?php

namespace App\Nodes\Integrations\Slack;

use App\Models\Runs\Run;

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

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['access_token', 'channel'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'channel' => ['type' => 'string'],
                'limit' => ['type' => 'integer'],
                'oldest' => ['type' => 'string'],
                'latest' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get('conversations.history', $config, array_filter([
            'channel' => $config['channel'],
            'limit' => $config['limit'] ?? null,
            'oldest' => $config['oldest'] ?? null,
            'latest' => $config['latest'] ?? null,
        ], fn ($value) => $value !== null));
    }
}
