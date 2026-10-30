<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloSearchConversationsNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_search_conversations';
    }

    public function name(): string
    {
        return 'Apollo: Search Conversations';
    }

    public function description(): string
    {
        return 'Finds recorded calls and meetings from Apollo\'s conversation intelligence.';
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
                'conversation_type' => Field::select('Type', ['video_conference' => 'Meeting', 'phone_call' => 'Phone call']),
                'account_id' => Field::dynamic('Account', 'apollo.accounts'),
                'contact_ids' => Field::advanced(Field::list('Contact ids')),
                'limit' => Field::integer('Max results', null, 25, 1, 100),
                'page' => Field::advanced(Field::integer('Page', null, 1, 1)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = [...$this->only($config, ['conversation_type', 'account_id']), 'contact_ids' => $this->stringList($config, 'contact_ids') ?: null, 'page' => max(1, (int) ($config['page'] ?? 1)), 'num_fetch_result' => max(1, min(100, (int) ($config['limit'] ?? 25)))];

        return $this->listResult($this->apollo($run, $config, 'POST', '/conversations/search', [], $body), 'conversations');
    }
}
