<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskDeleteSuspendedTicketNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_delete_suspended_ticket';
    }

    public function name(): string
    {
        return 'Zendesk: Delete Suspended Ticket';
    }

    public function description(): string
    {
        return 'Deletes a suspended ticket for good.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['suspended_ticket_id'],
            'properties' => [
                ...$this->credentialFields(),
                'suspended_ticket_id' => Field::text('Suspended ticket id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'suspended_ticket_id', 'The suspended ticket');
        $this->zendesk($run, $config, 'DELETE', "/suspended_tickets/{$id}");

        return ['deleted' => true, 'id' => (int) $id];
    }
}
