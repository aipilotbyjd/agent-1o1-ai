<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskToggleTimerNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_toggle_timer';
    }

    public function name(): string
    {
        return 'Freshdesk: Toggle Timer';
    }

    public function description(): string
    {
        return 'Starts or stops the timer on a time entry.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'time_entry_id', 'The time entry');

        return $this->freshdesk($run, $config, 'PUT', "/time_entries/{$id}/toggle_timer");
    }
}
