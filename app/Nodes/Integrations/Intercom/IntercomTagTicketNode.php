<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomTagTicketNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_tag_ticket';
    }

    public function name(): string
    {
        return 'Intercom: Add Tag To Ticket';
    }

    public function description(): string
    {
        return 'Puts a tag on a ticket.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'tag_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'tag_id' => Field::dynamic('Tag', 'intercom.tags', null, 'VIP'),
                'admin_id' => Field::advanced(Field::dynamic('As teammate', 'intercom.admins')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'ticket_id', 'The ticket');

        return $this->intercom($run, $config, 'POST', "/tickets/{$id}/tags", [], ['id' => $this->safeId($config, 'tag_id', 'The tag'), 'admin_id' => $this->adminId($run, $config)]);
    }
}
