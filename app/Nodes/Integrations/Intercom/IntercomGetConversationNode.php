<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomGetConversationNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_get_conversation';
    }

    public function name(): string
    {
        return 'Intercom: Get Conversation';
    }

    public function description(): string
    {
        return 'Reads a conversation with its messages, or says it was not found.';
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
        $id = $this->safeId($config, 'conversation_id', 'The conversation');

        return $this->findOne($run, $config, "/conversations/{$id}", $id, $this->shapeConversation(...));
    }
}
