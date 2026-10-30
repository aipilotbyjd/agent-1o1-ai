<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskSearchTicketsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_search_tickets';
    }

    public function name(): string
    {
        return 'Zendesk: Search Tickets';
    }

    public function description(): string
    {
        return 'Finds tickets with Zendesk\'s search language, e.g. status:open priority:high.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::text('Search', 'Zendesk search terms. type:ticket is added for you.', 'status:open requester:ada@example.com'),
                'sort_by' => Field::advanced(Field::select('Sort by', ['created_at' => 'Created', 'updated_at' => 'Last changed', 'priority' => 'Priority', 'status' => 'Status', 'ticket_type' => 'Type'], null, 'updated_at')),
                'sort_order' => Field::advanced(Field::select('Order', ['desc' => 'Newest first', 'asc' => 'Oldest first'], null, 'desc')),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = 'type:ticket '.$this->requiredString($config, 'query', 'The search');
        $rows = $this->listAll($run, $config, '/search', 'results', ['query' => $query, 'sort_by' => $config['sort_by'] ?? 'updated_at', 'sort_order' => ($config['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc'], $this->limit($config, 100));

        return ['tickets' => array_map($this->shapeTicket(...), $rows), 'count' => count($rows)];
    }
}
