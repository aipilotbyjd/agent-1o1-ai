<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomConvertConversationNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_convert_conversation';
    }

    public function name(): string
    {
        return 'Intercom: Convert Conversation To Ticket';
    }

    public function description(): string
    {
        return 'Turns a conversation into a ticket of a type.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['conversation_id', 'ticket_type_id'],
            'properties' => [
                ...$this->credentialFields(),
                'conversation_id' => Field::text('Conversation id'),
                'ticket_type_id' => Field::dynamic('Ticket type', 'intercom.ticket_types'),
                'attributes' => Field::advanced(Field::json('Ticket attributes (JSON)')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'conversation_id', 'The conversation');

        return $this->shapeTicket($this->intercom($run, $config, 'POST', "/conversations/{$id}/convert", [], $this->present(['ticket_type_id' => $this->safeId($config, 'ticket_type_id', 'The ticket type'), 'attributes' => $this->jsonObject($config, 'attributes', 'The ticket attributes') ?: null])));
    }
}
