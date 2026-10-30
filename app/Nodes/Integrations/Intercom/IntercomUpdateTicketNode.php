<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomUpdateTicketNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_update_ticket';
    }

    public function name(): string
    {
        return 'Intercom: Update Ticket';
    }

    public function description(): string
    {
        return 'Changes a ticket\'s attributes, state or assignee. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'ticket_state_id' => Field::advanced(Field::dynamic('State', 'intercom.ticket_states')),
                'attributes' => Field::advanced(Field::json('Ticket attributes (JSON)')),
                'open' => Field::advanced(Field::boolean('Open')),
                'assignee_id' => Field::advanced(Field::dynamic('Assignee', 'intercom.admins')),
                'admin_id' => Field::advanced(Field::dynamic('As teammate', 'intercom.admins')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'ticket_id', 'The ticket');
        $assignee = $this->text($config, 'assignee_id');
        $body = $this->present(['ticket_attributes' => $this->jsonObject($config, 'attributes', 'The ticket attributes') ?: null, 'ticket_state_id' => $this->text($config, 'ticket_state_id'), 'open' => $this->flag($config, 'open'), 'assignee_id' => $assignee, 'admin_id' => $assignee !== null ? (int) $this->adminId($run, $config) : null]);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->shapeTicket($this->intercom($run, $config, 'PUT', "/tickets/{$id}", [], $body));
    }
}
