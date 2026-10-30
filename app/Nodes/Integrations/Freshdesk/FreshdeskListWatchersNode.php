<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskListWatchersNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_watchers';
    }

    public function name(): string
    {
        return 'Freshdesk: List Watchers';
    }

    public function description(): string
    {
        return 'Lists the agents watching a ticket.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $ids = (array) ($this->freshdesk($run, $config, 'GET', "/tickets/{$id}/watchers")['watcher_ids'] ?? []);

        return ['watcher_ids' => $ids, 'count' => count($ids)];
    }
}
