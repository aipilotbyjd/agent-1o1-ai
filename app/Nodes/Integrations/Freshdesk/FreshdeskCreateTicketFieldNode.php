<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskCreateTicketFieldNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_create_ticket_field';
    }

    public function name(): string
    {
        return 'Freshdesk: Create Ticket Field';
    }

    public function description(): string
    {
        return 'Adds a custom ticket field.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['label', 'label_for_customers', 'type'],
            'properties' => [
                ...$this->credentialFields(),
                'label' => Field::text('Label for agents'),
                'label_for_customers' => Field::text('Label for customers'),
                'type' => Field::select('Type', ['custom_text' => 'Single line', 'custom_paragraph' => 'Paragraph', 'custom_checkbox' => 'Checkbox', 'custom_number' => 'Number', 'custom_date' => 'Date', 'custom_decimal' => 'Decimal', 'custom_dropdown' => 'Dropdown'], null, 'custom_text'),
                'choices' => Field::advanced(Field::json('Choices (JSON)', 'For a dropdown, e.g. [{"value": "Refund", "position": 1}].', 'array')),
                'customers_can_edit' => Field::advanced(Field::boolean('Customers can edit')),
                'displayed_to_customers' => Field::advanced(Field::boolean('Shown to customers')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $choices = is_string($config['choices'] ?? null) ? json_decode((string) $config['choices'], true) : ($config['choices'] ?? null);

        return $this->freshdesk($run, $config, 'POST', '/admin/ticket_fields', [], $this->present(['label' => $this->requiredString($config, 'label', 'The label'), 'label_for_customers' => $this->requiredString($config, 'label_for_customers', 'The customer label'), 'type' => $config['type'] ?? 'custom_text', 'choices' => is_array($choices) ? $choices : null, 'customers_can_edit' => $this->flag($config, 'customers_can_edit'), 'displayed_to_customers' => $this->flag($config, 'displayed_to_customers')]));
    }
}
