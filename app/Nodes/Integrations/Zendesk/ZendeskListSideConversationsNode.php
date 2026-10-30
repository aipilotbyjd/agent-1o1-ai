<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class ZendeskListSideConversationsNode extends AbstractZendeskNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'zendesk_list_side_conversations';
    }

    public function name(): string
    {
        return 'Zendesk: List Side Conversations';
    }

    public function description(): string
    {
        return 'Lists a ticket\'s side conversations. As a trigger it starts the flow when one is opened. Needs the Collaboration add-on.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');

        return $this->listing($run, $config, "/tickets/{$id}/side_conversations", 'side_conversations');
    }

    protected function triggerItemsPath(): ?string
    {
        return 'side_conversations';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 500];
    }

    public function triggerName(): string
    {
        return 'Zendesk: New side conversation';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a ticket gets a side conversation. Checked every few minutes.';
    }
}
