<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogUpdateDashboardListNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_update_dashboard_list';
    }

    public function name(): string
    {
        return 'Datadog: Update Dashboard List';
    }

    public function description(): string
    {
        return 'Renames a dashboard list.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => Field::text('List id'),
                'name' => Field::text('Name'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'list_id', 'The list id');

        return $this->datadog($run, $config, 'PUT', "/api/v1/dashboard/lists/manual/{$id}", [], ['name' => $this->requiredString($config, 'name', 'The name')]);
    }
}
