<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloGetConversationNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_get_conversation';
    }

    public function name(): string
    {
        return 'Apollo: Get Conversation';
    }

    public function description(): string
    {
        return 'Reads the details of one recorded conversation.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['conversation_id'],
            'properties' => [
                ...$this->credentialFields(),
                'conversation_id' => Field::text('Conversation id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->apollo($run, $config, 'GET', '/conversations/'.$this->recordId($config, 'conversation_id', 'The conversation id'));
    }
}
