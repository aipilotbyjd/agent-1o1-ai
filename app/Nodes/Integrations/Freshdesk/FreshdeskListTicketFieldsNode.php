<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FreshdeskListTicketFieldsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_ticket_fields';
    }

    public function name(): string
    {
        return 'Freshdesk: List Ticket Fields';
    }

    public function description(): string
    {
        return 'Lists the system and custom fields of tickets, with their API names.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = $this->freshdesk($run, $config, 'GET', '/admin/ticket_fields');

        return ['ticket_fields' => array_values($rows), 'count' => count($rows)];
    }
}
