<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskSearchTicketsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_search_tickets';
    }

    public function name(): string
    {
        return 'Freshdesk: Search Tickets';
    }

    public function description(): string
    {
        return 'Finds tickets with Freshdesk\'s filter language, e.g. priority:3 AND status:2.';
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
                'query' => Field::text('Filter', 'Field names as in List Ticket Fields. Text values in single quotes.', 'priority:3 AND status:2'),
                'limit' => Field::integer('Max results', null, 30, 1, 300),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = $this->searchAll($run, $config, '/search/tickets', $this->requiredString($config, 'query', 'The filter'), $this->limit($config, 30, 300));

        return ['tickets' => array_map($this->shapeTicket(...), $rows), 'count' => count($rows)];
    }
}
