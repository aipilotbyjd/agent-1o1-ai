<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskMergeTicketsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_merge_tickets';
    }

    public function name(): string
    {
        return 'Freshdesk: Merge Tickets';
    }

    public function description(): string
    {
        return 'Merges tickets into a primary ticket.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['primary_id', 'ticket_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'primary_id' => Field::text('Primary ticket id', 'The ticket that stays.'),
                'ticket_ids' => Field::text('Tickets to merge', 'Separated by commas. Include the primary or not, it does not matter.', '101, 102'),
                'note' => Field::advanced(Field::textarea('Note on the primary ticket')),
                'convert_recipients_to_cc' => Field::advanced(Field::boolean('Copy their recipients', 'Adds the merged tickets\' people as CC on the primary.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $primary = (int) $this->numericId($config, 'primary_id', 'The primary ticket');
        $note = $this->text($config, 'note');

        return ['merged' => true, 'primary_id' => $primary, ...$this->freshdesk($run, $config, 'PUT', '/tickets/merge', [], $this->present(['primary_id' => $primary, 'ticket_ids' => array_values(array_unique([$primary, ...$this->idList($config, 'ticket_ids', 'The tickets to merge')])), 'convert_recepients_to_cc' => $this->flag($config, 'convert_recipients_to_cc'), 'note_in_primary' => $note !== null ? ['body' => $note, 'private' => true] : null]))];
    }
}
