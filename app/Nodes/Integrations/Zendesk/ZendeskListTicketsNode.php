<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskListTicketsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_list_tickets';
    }

    public function name(): string
    {
        return 'Zendesk: List Tickets';
    }

    public function description(): string
    {
        return 'Lists tickets, newest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'sort_by' => Field::advanced(Field::select('Newest by', ['created_at' => 'Created', 'updated_at' => 'Last changed'], null, 'created_at')),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $sort = ($config['sort_by'] ?? 'created_at') === 'updated_at' ? 'updated_at' : 'created_at';

        return $this->listing($run, $config, '/tickets', 'tickets', ['sort_by' => $sort, 'sort_order' => 'desc'], $this->shapeTicket(...));
    }
}
