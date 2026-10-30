<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskListAuditsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_list_audits';
    }

    public function name(): string
    {
        return 'Zendesk: List Ticket Audits';
    }

    public function description(): string
    {
        return 'Lists the change history of one ticket: field updates, status changes and assignments.';
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
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');

        return $this->listing($run, $config, "/tickets/{$id}/audits", 'audits', ['sort_order' => 'desc']);
    }
}
