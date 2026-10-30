<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskUpdateTicketFieldNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_update_ticket_field';
    }

    public function name(): string
    {
        return 'Freshdesk: Update Ticket Field';
    }

    public function description(): string
    {
        return 'Changes a ticket field. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['field_id'],
            'properties' => [
                ...$this->credentialFields(),
                'field_id' => Field::text('Field id'),
                'label' => Field::advanced(Field::text('Label for agents')),
                'label_for_customers' => Field::advanced(Field::text('Label for customers')),
                'position' => Field::advanced(Field::integer('Position', null, null)),
                'choices' => Field::advanced(Field::json('Choices (JSON)', 'e.g. [{"id": 129, "value": "Returns"}, {"id": 130, "deleted": true}].', 'array')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'field_id', 'The field');
        $choices = is_string($config['choices'] ?? null) ? json_decode((string) $config['choices'], true) : ($config['choices'] ?? null);
        $body = $this->present(['label' => $this->text($config, 'label'), 'label_for_customers' => $this->text($config, 'label_for_customers'), 'position' => $this->number($config, 'position'), 'choices' => is_array($choices) ? $choices : null]);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->freshdesk($run, $config, 'PUT', "/admin/ticket_fields/{$id}", [], $body);
    }
}
