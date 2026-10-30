<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskDeleteTimeEntryNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_delete_time_entry';
    }

    public function name(): string
    {
        return 'Freshdesk: Delete Time Entry';
    }

    public function description(): string
    {
        return 'Deletes a time entry.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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

        return $this->destroy($run, $config, "/time_entries/{$id}", $id);
    }
}
