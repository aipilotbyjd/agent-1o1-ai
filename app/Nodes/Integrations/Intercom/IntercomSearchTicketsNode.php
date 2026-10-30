<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomSearchTicketsNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_search_tickets';
    }

    public function name(): string
    {
        return 'Intercom: Search Tickets';
    }

    public function description(): string
    {
        return 'Finds tickets by a field, such as title, state or ticket_type_id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['field', 'value'],
            'properties' => [
                ...$this->credentialFields(),
                'field' => Field::text('Field', 'e.g. title, state, open, ticket_type_id, contact_ids, ticket_attribute.123.', 'title'),
                'operator' => Field::advanced(Field::select('Match', ['=' => 'Is', '!=' => 'Is not', '~' => 'Contains', '!~' => 'Does not contain', '^' => 'Starts with', '$' => 'Ends with'], null, '=')),
                'value' => Field::text('Value'),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = $this->cursorAll($run, $config, '/tickets/search', 'tickets', [], ['field' => $this->requiredString($config, 'field', 'The field'), 'operator' => trim((string) ($config['operator'] ?? '=')), 'value' => $this->requiredString($config, 'value', 'The value')], $this->limit($config, 50));

        return ['tickets' => array_map($this->shapeTicket(...), $rows), 'count' => count($rows)];
    }
}
