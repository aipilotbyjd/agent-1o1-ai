<?php

namespace App\Nodes\Integrations\Gmail;

use App\Models\Runs\Run;

class GmailListMessagesNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_list_messages';
    }

    public function name(): string
    {
        return 'Gmail: List Messages';
    }

    public function description(): string
    {
        return 'Lists messages in the inbox matching a query.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['access_token'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'query' => ['type' => 'string'],
                'max_results' => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get('/users/me/messages', $config, [
            'q' => $config['query'] ?? '',
            'maxResults' => $config['max_results'] ?? 10,
        ]);
    }
}
