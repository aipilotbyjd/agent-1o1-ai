<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskGetAuditNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_get_audit';
    }

    public function name(): string
    {
        return 'Zendesk: Get Ticket Audit';
    }

    public function description(): string
    {
        return 'Reads one change record of a ticket.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'audit_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'audit_id' => Field::text('Audit id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $audit = $this->numericId($config, 'audit_id', 'The audit');

        return $this->findOne($run, $config, "/tickets/{$id}/audits/{$audit}", 'audit', $audit);
    }
}
