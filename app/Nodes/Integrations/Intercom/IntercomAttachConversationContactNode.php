<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomAttachConversationContactNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_attach_conversation_contact';
    }

    public function name(): string
    {
        return 'Intercom: Attach Contact To Conversation';
    }

    public function description(): string
    {
        return 'Adds a contact to a conversation.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['conversation_id', 'contact_id'],
            'properties' => [
                ...$this->credentialFields(),
                'conversation_id' => Field::text('Conversation id'),
                'contact_id' => Field::text('Contact id'),
                'admin_id' => Field::advanced(Field::dynamic('As teammate', 'intercom.admins')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'conversation_id', 'The conversation');

        return $this->intercom($run, $config, 'POST', "/conversations/{$id}/customers", [], ['admin_id' => $this->adminId($run, $config), 'customer' => ['intercom_user_id' => $this->safeId($config, 'contact_id', 'The contact')]]);
    }
}
