<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskDeleteTicketFieldOptionNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_delete_ticket_field_option';
    }

    public function name(): string
    {
        return 'Zendesk: Remove Ticket Field Option';
    }

    public function description(): string
    {
        return 'Removes a choice from a dropdown ticket field.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_field_id', 'option_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_field_id' => Field::text('Field id'),
                'option_id' => Field::text('Option id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_field_id', 'The field');
        $option = $this->numericId($config, 'option_id', 'The option');
        $this->zendesk($run, $config, 'DELETE', "/ticket_fields/{$id}/options/{$option}");

        return ['deleted' => true, 'id' => (int) $option];
    }
}
