<?php

namespace App\Nodes\Integrations\Slack;

use App\Models\Runs\Run;

class SlackInviteToChannelNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_invite_to_channel';
    }

    public function name(): string
    {
        return 'Slack: Invite to Channel';
    }

    public function description(): string
    {
        return 'Invites one or more users to a Slack channel.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel', 'users'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'integer'],
                'channel' => ['type' => 'string'],
                // Comma-separated Slack user IDs, per Slack's own API shape.
                'users' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, 'conversations.invite', $config, [
            'channel' => $config['channel'],
            'users' => $config['users'],
        ]);
    }
}
