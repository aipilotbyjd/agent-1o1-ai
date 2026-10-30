<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class ZendeskListSideConversationEventsNode extends AbstractZendeskNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'zendesk_list_side_conversation_events';
    }

    public function name(): string
    {
        return 'Zendesk: List Side Conversation Events';
    }

    public function description(): string
    {
        return 'Lists the messages and changes of a side conversation. As a trigger it starts the flow for each new reply.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $side = $this->safeId($config, 'side_conversation_id', 'The side conversation');

        return $this->listing($run, $config, "/tickets/{$id}/side_conversations/{$side}/events", 'events');
    }

    protected function triggerItemsPath(): ?string
    {
        return 'events';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 500];
    }

    public function triggerName(): string
    {
        return 'Zendesk: New side conversation reply';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a side conversation gets a new message or change. Checked every few minutes.';
    }
}
