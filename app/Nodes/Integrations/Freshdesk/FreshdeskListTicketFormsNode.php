<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FreshdeskListTicketFormsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_ticket_forms';
    }

    public function name(): string
    {
        return 'Freshdesk: List Ticket Forms';
    }

    public function description(): string
    {
        return 'Lists the account\'s ticket forms.';
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
        $rows = $this->freshdesk($run, $config, 'GET', '/ticket-forms');

        return ['ticket_forms' => array_values($rows), 'count' => count($rows)];
    }
}
