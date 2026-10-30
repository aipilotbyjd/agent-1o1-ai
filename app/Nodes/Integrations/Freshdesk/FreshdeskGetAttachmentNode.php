<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetAttachmentNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_attachment';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Attachment';
    }

    public function description(): string
    {
        return 'Finds one attachment of a ticket and gives its name, size and download address, which works for a limited time.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'attachment_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'attachment_id' => Field::text('Attachment id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $wanted = $this->numericId($config, 'attachment_id', 'The attachment');
        foreach ($this->ticketAttachments($run, $config) as $attachment) {
            if ((string) ($attachment['id'] ?? '') === $wanted) {
                return ['found' => true, ...$attachment];
            }
        }

        return ['found' => false, 'id' => $wanted];
    }
}
