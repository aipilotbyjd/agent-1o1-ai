<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class FreshdeskCreateTimeEntryNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_create_time_entry';
    }

    public function name(): string
    {
        return 'Freshdesk: Create Time Entry';
    }

    public function description(): string
    {
        return 'Logs time on a ticket, or starts a timer when no time is given.';
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
                'time_spent' => Field::text('Time spent', 'hh:mm', '01:30'),
                'note' => Field::advanced(Field::textarea('Note')),
                'agent_id' => Field::advanced(Field::dynamic('Agent', 'freshdesk.agents')),
                'billable' => Field::advanced(Field::boolean('Billable')),
                'executed_at' => Field::advanced(Field::datetime('Done at')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');

        return $this->freshdesk($run, $config, 'POST', "/tickets/{$id}/time_entries", [], $this->present(['time_spent' => $this->text($config, 'time_spent'), 'note' => $this->text($config, 'note'), 'agent_id' => $this->optionalId($config, 'agent_id', 'The agent'), 'billable' => $this->flag($config, 'billable'), 'executed_at' => filled($config['executed_at'] ?? null) ? Carbon::parse((string) $config['executed_at'])->utc()->format('Y-m-d\TH:i:s\Z') : null]));
    }
}
