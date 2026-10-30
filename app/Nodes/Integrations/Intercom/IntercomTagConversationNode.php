<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomTagConversationNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_tag_conversation';
    }

    public function name(): string
    {
        return 'Intercom: Add Tag To Conversation';
    }

    public function description(): string
    {
        return 'Puts a tag on a conversation.';
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

        return $this->intercom($run, $config, 'POST', "/conversations/{$id}/tags", [], ['id' => $this->safeId($config, 'tag_id', 'The tag'), 'admin_id' => $this->adminId($run, $config)]);
    }
}
