<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskListTicketFieldOptionsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_list_ticket_field_options';
    }

    public function name(): string
    {
        return 'Zendesk: List Ticket Field Options';
    }

    public function description(): string
    {
        return 'Lists the choices of a dropdown ticket field.';
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
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_field_id', 'The field');

        return $this->listing($run, $config, "/ticket_fields/{$id}/options", 'custom_field_options');
    }
}
