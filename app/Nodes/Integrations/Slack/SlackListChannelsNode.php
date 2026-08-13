<?php

namespace App\Nodes\Integrations\Slack;

use App\Models\Runs\Run;

class SlackListChannelsNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_list_channels';
    }

    public function name(): string
    {
        return 'Slack: List Channels';
    }

    public function description(): string
    {
        return 'Lists channels in the Slack workspace, optionally filtered by type.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'integer'],
                'types' => ['type' => 'string'],
                'limit' => ['type' => 'integer'],
                'cursor' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get($run, 'conversations.list', $config, array_filter([
            'types' => $config['types'] ?? null,
            'limit' => $config['limit'] ?? null,
            'cursor' => $config['cursor'] ?? null,
        ], fn ($value) => $value !== null));
    }
}
