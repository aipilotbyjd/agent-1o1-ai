<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskDeleteTicketFieldNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_delete_ticket_field';
    }

    public function name(): string
    {
        return 'Freshdesk: Delete Ticket Field';
    }

    public function description(): string
    {
        return 'Deletes a custom ticket field.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['field_id'],
            'properties' => [
                ...$this->credentialFields(),
                'field_id' => Field::text('Field id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'field_id', 'The field');

        return $this->destroy($run, $config, "/admin/ticket_fields/{$id}", $id);
    }
}
