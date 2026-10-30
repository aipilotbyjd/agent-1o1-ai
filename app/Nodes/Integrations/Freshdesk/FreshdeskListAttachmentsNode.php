<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskListAttachmentsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_attachments';
    }

    public function name(): string
    {
        return 'Freshdesk: List Ticket Attachments';
    }

    public function description(): string
    {
        return 'Lists the attachments on a ticket and all its conversations.';
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
        $attachments = $this->ticketAttachments($run, $config);

        return ['attachments' => $attachments, 'count' => count($attachments)];
    }
}
