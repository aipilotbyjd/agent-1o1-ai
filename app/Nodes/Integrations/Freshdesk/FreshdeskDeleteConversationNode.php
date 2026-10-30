<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskDeleteConversationNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_delete_conversation';
    }

    public function name(): string
    {
        return 'Freshdesk: Delete Conversation';
    }

    public function description(): string
    {
        return 'Deletes a reply or a note.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $id = $this->numericId($config, 'conversation_id', 'The conversation');

        return $this->destroy($run, $config, "/conversations/{$id}", $id);
    }
}
