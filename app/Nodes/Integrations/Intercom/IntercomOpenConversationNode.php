<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomOpenConversationNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_open_conversation';
    }

    public function name(): string
    {
        return 'Intercom: Open Conversation';
    }

    public function description(): string
    {
        return 'Reopens a snoozed or closed conversation.';
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
                'admin_id' => Field::advanced(Field::dynamic('As teammate', 'intercom.admins')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->managePart($run, $config, 'open');
    }
}
