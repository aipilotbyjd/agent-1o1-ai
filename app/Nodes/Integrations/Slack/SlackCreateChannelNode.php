<?php

namespace App\Nodes\Integrations\Slack;

use App\Models\Runs\Run;

class SlackCreateChannelNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_create_channel';
    }

    public function name(): string
    {
        return 'Slack: Create Channel';
    }

    public function description(): string
    {
        return 'Creates a new Slack channel, public or private.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'is_private' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, 'conversations.create', $config, array_filter([
            'name' => $config['name'],
            'is_private' => $config['is_private'] ?? null,
        ], fn ($value) => $value !== null));
    }
}
