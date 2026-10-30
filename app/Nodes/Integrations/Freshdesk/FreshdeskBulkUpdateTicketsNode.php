<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskBulkUpdateTicketsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_bulk_update_tickets';
    }

    public function name(): string
    {
        return 'Freshdesk: Update Multiple Tickets';
    }

    public function description(): string
    {
        return 'Makes the same change to many tickets in the background. Answers with a job to check.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_ids' => Field::text('Ticket ids', 'Separated by commas.', '101, 102'),
                'status' => Field::advanced(Field::select('Status', ['2' => 'Open', '3' => 'Pending', '4' => 'Resolved', '5' => 'Closed'])),
                'priority' => Field::advanced(Field::select('Priority', ['1' => 'Low', '2' => 'Medium', '3' => 'High', '4' => 'Urgent'])),
                'group_id' => Field::advanced(Field::dynamic('Group', 'freshdesk.groups')),
                'responder_id' => Field::advanced(Field::dynamic('Agent', 'freshdesk.agents')),
                'type' => Field::advanced(Field::text('Type')),
                'reply' => Field::advanced(Field::textarea('Reply to send on each')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $properties = $this->present(['status' => $this->number($config, 'status'), 'priority' => $this->number($config, 'priority'), 'group_id' => $this->optionalId($config, 'group_id', 'The group'), 'responder_id' => $this->optionalId($config, 'responder_id', 'The agent'), 'type' => $this->text($config, 'type')]);
        $reply = $this->text($config, 'reply');
        if ($properties === [] && $reply === null) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->freshdesk($run, $config, 'POST', '/tickets/bulk_update', [], ['bulk_action' => $this->present(['ids' => $this->idList($config, 'ticket_ids', 'The tickets'), 'properties' => $properties, 'reply' => $reply !== null ? ['body' => $reply] : null])]);
    }
}
