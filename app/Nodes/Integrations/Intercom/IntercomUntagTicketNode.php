<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomUntagTicketNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_untag_ticket';
    }

    public function name(): string
    {
        return 'Intercom: Remove Tag From Ticket';
    }

    public function description(): string
    {
        return 'Takes a tag off a ticket.';
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
        $tag = $this->safeId($config, 'tag_id', 'The tag');

        return $this->intercom($run, $config, 'DELETE', "/tickets/{$id}/tags/{$tag}", [], ['admin_id' => $this->adminId($run, $config)]);
    }
}
