<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskUpdateSideConversationNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_update_side_conversation';
    }

    public function name(): string
    {
        return 'Zendesk: Update Side Conversation';
    }

    public function description(): string
    {
        return 'Closes, reopens or renames a side conversation.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'side_conversation_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'side_conversation_id' => Field::text('Side conversation id'),
                'state' => Field::advanced(Field::select('State', ['open' => 'Open', 'closed' => 'Closed'])),
                'subject' => Field::advanced(Field::text('Subject')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $side = $this->safeId($config, 'side_conversation_id', 'The side conversation');
        $body = $this->present(['state' => $this->text($config, 'state'), 'subject' => $this->text($config, 'subject')]);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }
        $result = $this->zendesk($run, $config, 'PUT', "/tickets/{$id}/side_conversations/{$side}", [], ['side_conversation' => $body]);

        return (array) ($result['side_conversation'] ?? $result);
    }
}
