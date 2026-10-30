<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class FreshdeskListTimeEntriesNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_time_entries';
    }

    public function name(): string
    {
        return 'Freshdesk: List Time Entries';
    }

    public function description(): string
    {
        return 'Lists time entries, optionally of one ticket, agent or company.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::advanced(Field::text('Ticket id')),
                'agent_id' => Field::advanced(Field::dynamic('Agent', 'freshdesk.agents')),
                'company_id' => Field::advanced(Field::dynamic('Company', 'freshdesk.companies')),
                'executed_after' => Field::advanced(Field::datetime('Done after')),
                'executed_before' => Field::advanced(Field::datetime('Done before')),
                'billable' => Field::advanced(Field::boolean('Only billable')),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $at = fn (string $key): ?string => filled($config[$key] ?? null) ? Carbon::parse((string) $config[$key])->utc()->format('Y-m-d\TH:i:s\Z') : null;
        $ticket = $this->optionalId($config, 'ticket_id', 'The ticket');

        return $this->listing($run, $config, $ticket !== null ? "/tickets/{$ticket}/time_entries" : '/time_entries', 'time_entries', ['agent_id' => $this->optionalId($config, 'agent_id', 'The agent'), 'company_id' => $this->optionalId($config, 'company_id', 'The company'), 'executed_after' => $at('executed_after'), 'executed_before' => $at('executed_before'), 'billable' => $this->flag($config, 'billable') ? 'true' : null]);
    }
}
