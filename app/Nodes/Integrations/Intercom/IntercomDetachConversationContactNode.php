<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomDetachConversationContactNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_detach_conversation_contact';
    }

    public function name(): string
    {
        return 'Intercom: Detach Contact From Conversation';
    }

    public function description(): string
    {
        return 'Removes a contact from a conversation.';
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
        $contact = $this->safeId($config, 'contact_id', 'The contact');

        return $this->intercom($run, $config, 'DELETE', "/conversations/{$id}/customers/{$contact}", [], ['admin_id' => $this->adminId($run, $config)]);
    }
}
