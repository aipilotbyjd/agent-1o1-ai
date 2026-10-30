<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DatadogDeleteDashboardNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_delete_dashboard';
    }

    public function name(): string
    {
        return 'Datadog: Delete Dashboard';
    }

    public function description(): string
    {
        return 'Deletes a dashboard.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['dashboard_id'],
            'properties' => [
                ...$this->credentialFields(),
                'dashboard_id' => $this->dashboardField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'dashboard_id', 'The dashboard');
        $this->datadog($run, $config, 'DELETE', "/api/v1/dashboard/{$id}");

        return ['deleted' => true, 'id' => rawurldecode($id)];
    }
}
