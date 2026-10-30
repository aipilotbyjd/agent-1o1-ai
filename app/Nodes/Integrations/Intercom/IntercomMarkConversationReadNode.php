<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomMarkConversationReadNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_mark_conversation_read';
    }

    public function name(): string
    {
        return 'Intercom: Mark Conversation As Read';
    }

    public function description(): string
    {
        return 'Marks a conversation as read.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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

        return $this->shapeConversation($this->intercom($run, $config, 'PUT', "/conversations/{$id}", [], ['read' => true]));
    }
}
