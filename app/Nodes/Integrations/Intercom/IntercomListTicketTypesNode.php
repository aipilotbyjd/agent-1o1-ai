<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class IntercomListTicketTypesNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_list_ticket_types';
    }

    public function name(): string
    {
        return 'Intercom: List Ticket Types';
    }

    public function description(): string
    {
        return 'Lists the ticket types, with their attributes.';
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
        $rows = (array) ($this->intercom($run, $config, 'GET', '/ticket_types')['data'] ?? []);

        return ['ticket_types' => $rows, 'count' => count($rows)];
    }
}
