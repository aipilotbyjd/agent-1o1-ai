<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskRemoveTicketTagsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_remove_ticket_tags';
    }

    public function name(): string
    {
        return 'Freshdesk: Remove Ticket Tags';
    }

    public function description(): string
    {
        return 'Removes tags from a ticket.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'tags'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'tags' => Field::text('Tags', 'Separated by commas.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $current = (array) ($this->freshdesk($run, $config, 'GET', "/tickets/{$id}")['tags'] ?? []);

        return $this->changeTicket($run, $config, ['tags' => array_values(array_diff($current, $this->words($config, 'tags')))]);
    }
}
