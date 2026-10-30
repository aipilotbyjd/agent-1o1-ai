<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskUpdateTimeEntryNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_update_time_entry';
    }

    public function name(): string
    {
        return 'Freshdesk: Update Time Entry';
    }

    public function description(): string
    {
        return 'Changes a time entry. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['time_entry_id'],
            'properties' => [
                ...$this->credentialFields(),
                'time_entry_id' => Field::text('Time entry id'),
                'time_spent' => Field::advanced(Field::text('Time spent', 'hh:mm', '01:30')),
                'note' => Field::advanced(Field::textarea('Note')),
                'agent_id' => Field::advanced(Field::dynamic('Agent', 'freshdesk.agents')),
                'billable' => Field::advanced(Field::boolean('Billable')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'time_entry_id', 'The time entry');
        $body = $this->present(['time_spent' => $this->text($config, 'time_spent'), 'note' => $this->text($config, 'note'), 'agent_id' => $this->optionalId($config, 'agent_id', 'The agent'), 'billable' => $this->flag($config, 'billable')]);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->freshdesk($run, $config, 'PUT', "/time_entries/{$id}", [], $body);
    }
}
