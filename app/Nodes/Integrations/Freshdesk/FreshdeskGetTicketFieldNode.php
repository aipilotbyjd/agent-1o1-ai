<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetTicketFieldNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_ticket_field';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Ticket Field';
    }

    public function description(): string
    {
        return 'Reads one ticket field.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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

        return $this->findOne($run, $config, "/admin/ticket_fields/{$id}", $id);
    }
}
