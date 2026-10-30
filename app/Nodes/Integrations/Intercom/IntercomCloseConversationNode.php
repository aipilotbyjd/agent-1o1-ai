<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomCloseConversationNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_close_conversation';
    }

    public function name(): string
    {
        return 'Intercom: Close Conversation';
    }

    public function description(): string
    {
        return 'Closes a conversation, optionally with a closing message.';
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
                'body' => Field::advanced(Field::text('Closing message')),
                'admin_id' => Field::advanced(Field::dynamic('As teammate', 'intercom.admins')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->managePart($run, $config, 'close', $this->present(['body' => $this->text($config, 'body')]));
    }
}
