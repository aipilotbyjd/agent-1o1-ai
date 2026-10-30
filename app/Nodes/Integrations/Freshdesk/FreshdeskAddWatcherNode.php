<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskAddWatcherNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_add_watcher';
    }

    public function name(): string
    {
        return 'Freshdesk: Add Watcher';
    }

    public function description(): string
    {
        return 'Adds an agent as a watcher on a ticket.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'user_id' => Field::dynamic('Agent', 'freshdesk.agents'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $this->freshdesk($run, $config, 'POST', "/tickets/{$id}/watch", [], $this->present(['user_id' => $this->optionalId($config, 'user_id', 'The agent')]));

        return ['watching' => true, 'ticket_id' => (int) $id];
    }
}
