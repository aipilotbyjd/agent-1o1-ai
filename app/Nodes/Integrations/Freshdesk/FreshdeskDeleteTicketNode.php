<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskDeleteTicketNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_delete_ticket';
    }

    public function name(): string
    {
        return 'Freshdesk: Delete Ticket';
    }

    public function description(): string
    {
        return 'Moves a ticket to the trash. Restore Ticket brings it back.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');

        return $this->destroy($run, $config, "/tickets/{$id}", $id);
    }
}
