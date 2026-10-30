<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskAssignTicketToGroupNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_assign_ticket_to_group';
    }

    public function name(): string
    {
        return 'Freshdesk: Assign Ticket To Group';
    }

    public function description(): string
    {
        return 'Gives a ticket to a group.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'group_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'group_id' => Field::dynamic('Group', 'freshdesk.groups'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->changeTicket($run, $config, ['group_id' => (int) $this->numericId($config, 'group_id', 'The group')]);
    }
}
