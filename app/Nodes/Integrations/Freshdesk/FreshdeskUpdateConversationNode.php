<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskUpdateConversationNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_update_conversation';
    }

    public function name(): string
    {
        return 'Freshdesk: Update Conversation';
    }

    public function description(): string
    {
        return 'Changes the text of a note.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['conversation_id', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'conversation_id' => Field::text('Conversation id'),
                'body' => Field::textarea('Text', 'HTML is kept.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'conversation_id', 'The conversation');

        return $this->shapeConversation($this->freshdesk($run, $config, 'PUT', "/conversations/{$id}", [], ['body' => $this->requiredString($config, 'body', 'The text')]));
    }
}
