<?php

namespace App\Nodes\Integrations\Slack;

use App\Models\Runs\Run;

class SlackListUsersNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_list_users';
    }

    public function name(): string
    {
        return 'Slack: List Users';
    }

    public function description(): string
    {
        return 'Lists users in the Slack workspace.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['access_token'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'limit' => ['type' => 'integer'],
                'cursor' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get('users.list', $config, array_filter([
            'limit' => $config['limit'] ?? null,
            'cursor' => $config['cursor'] ?? null,
        ], fn ($value) => $value !== null));
    }
}
