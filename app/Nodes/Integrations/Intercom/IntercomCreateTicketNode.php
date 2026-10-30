<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomCreateTicketNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_create_ticket';
    }

    public function name(): string
    {
        return 'Intercom: Create Ticket';
    }

    public function description(): string
    {
        return 'Creates a ticket of a type for a contact.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_type_id', 'contact_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_type_id' => Field::dynamic('Ticket type', 'intercom.ticket_types'),
                'contact_id' => Field::text('Contact id', 'Or give their email below.'),
                'email' => Field::advanced(Field::text('Or email')),
                'company_id' => Field::advanced(Field::text('Company id')),
                'attributes' => Field::json('Ticket attributes (JSON)', 'e.g. {"_default_title_": "Refund", "_default_description_": "..."}.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $contact = $this->present(['id' => $this->text($config, 'contact_id'), 'email' => filled($config['email'] ?? null) ? $this->email($config) : null]);
        if ($contact === []) {
            throw new \InvalidArgumentException('Say which contact the ticket is for.');
        }

        return $this->shapeTicket($this->intercom($run, $config, 'POST', '/tickets', [], $this->present(['ticket_type_id' => $this->safeId($config, 'ticket_type_id', 'The ticket type'), 'contacts' => [$contact], 'company_id' => $this->text($config, 'company_id'), 'ticket_attributes' => $this->jsonObject($config, 'attributes', 'The ticket attributes') ?: null])));
    }
}
