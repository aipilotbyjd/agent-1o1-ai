<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomUntagConversationNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_untag_conversation';
    }

    public function name(): string
    {
        return 'Intercom: Remove Tag From Conversation';
    }

    public function description(): string
    {
        return 'Takes a tag off a conversation.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['conversation_id', 'tag_id'],
            'properties' => [
                ...$this->credentialFields(),
                'conversation_id' => Field::text('Conversation id'),
                'tag_id' => Field::dynamic('Tag', 'intercom.tags', null, 'VIP'),
                'admin_id' => Field::advanced(Field::dynamic('As teammate', 'intercom.admins')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'conversation_id', 'The conversation');
        $tag = $this->safeId($config, 'tag_id', 'The tag');

        return $this->intercom($run, $config, 'DELETE', "/conversations/{$id}/tags/{$tag}", [], ['admin_id' => $this->adminId($run, $config)]);
    }
}
