<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

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

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::text('Search', 'Gmail search syntax, same as the Gmail search box.', 'from:boss@example.com is:unread newer_than:1d'),
                'max_results' => Field::integer('Max results', null, 10, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get($run, '/users/me/messages', $config, [
            'q' => $config['query'] ?? '',
            'maxResults' => $config['max_results'] ?? 10,
        ]);
    }
}
