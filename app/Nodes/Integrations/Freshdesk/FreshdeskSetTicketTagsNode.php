<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskSetTicketTagsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_set_ticket_tags';
    }

    public function name(): string
    {
        return 'Freshdesk: Set Ticket Tags';
    }

    public function description(): string
    {
        return 'Replaces all tags of a ticket.';
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
        return $this->changeTicket($run, $config, ['tags' => $this->words($config, 'tags')]);
    }
}
