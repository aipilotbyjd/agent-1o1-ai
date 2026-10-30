<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskAddNoteNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_add_note';
    }

    public function name(): string
    {
        return 'Freshdesk: Add Note';
    }

    public function description(): string
    {
        return 'Adds a note to a ticket, private unless you say otherwise.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'body' => Field::textarea('Note', 'HTML is kept.'),
                'public' => Field::advanced(Field::boolean('Public note', 'Off keeps it private.')),
                'notify_emails' => Field::advanced(Field::emails('Notify')),
                'user_id' => Field::advanced(Field::dynamic('As agent', 'freshdesk.agents')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');

        return $this->shapeConversation($this->freshdesk($run, $config, 'POST', "/tickets/{$id}/notes", [], $this->present(['body' => $this->requiredString($config, 'body', 'The note'), 'private' => ! filter_var($config['public'] ?? false, FILTER_VALIDATE_BOOLEAN), 'notify_emails' => $this->words($config, 'notify_emails'), 'user_id' => $this->optionalId($config, 'user_id', 'The agent')])));
    }
}
