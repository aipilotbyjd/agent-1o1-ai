<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskGetTicketFieldNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_get_ticket_field';
    }

    public function name(): string
    {
        return 'Zendesk: Get Ticket Field';
    }

    public function description(): string
    {
        return 'Reads one ticket field and its options.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_field_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_field_id' => Field::text('Field id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_field_id', 'The field');

        return $this->findOne($run, $config, "/ticket_fields/{$id}", 'ticket_field', $id);
    }
}
