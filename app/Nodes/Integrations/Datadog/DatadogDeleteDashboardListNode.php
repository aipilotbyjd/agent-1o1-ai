<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogDeleteDashboardListNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_delete_dashboard_list';
    }

    public function name(): string
    {
        return 'Datadog: Delete Dashboard List';
    }

    public function description(): string
    {
        return 'Deletes a dashboard list; its dashboards are kept.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => Field::text('List id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'list_id', 'The list id');
        $this->datadog($run, $config, 'DELETE', "/api/v1/dashboard/lists/manual/{$id}");

        return ['deleted' => true, 'id' => $id];
    }
}
