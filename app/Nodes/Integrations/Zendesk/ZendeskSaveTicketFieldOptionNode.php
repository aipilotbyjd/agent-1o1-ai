<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskSaveTicketFieldOptionNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_save_ticket_field_option';
    }

    public function name(): string
    {
        return 'Zendesk: Create or Update Ticket Field Option';
    }

    public function description(): string
    {
        return 'Adds a choice to a dropdown ticket field, or changes one when an option id is given.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_field_id', 'name', 'value'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_field_id' => Field::text('Field id'),
                'name' => Field::text('Label', 'Shown to people.', 'Gold plan'),
                'value' => Field::text('Value', 'The tag it sets.', 'gold_plan'),
                'option_id' => Field::advanced(Field::text('Option id', 'Set this to change an existing choice.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_field_id', 'The field');
        $option = $this->present(['id' => $this->optionalId($config, 'option_id', 'The option'), 'name' => $this->requiredString($config, 'name', 'The label'), 'value' => $this->requiredString($config, 'value', 'The value')]);

        return (array) ($this->zendesk($run, $config, 'POST', "/ticket_fields/{$id}/options", [], ['custom_field_option' => $option])['custom_field_option'] ?? []);
    }
}
