<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DatadogGetDashboardNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_dashboard';
    }

    public function name(): string
    {
        return 'Datadog: Get Dashboard';
    }

    public function description(): string
    {
        return 'Gets a dashboard with its widgets.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        $dashboard = $this->find($run, $config, '/api/v1/dashboard/'.$this->pathId($config, 'dashboard_id', 'The dashboard'));

        return $dashboard === null ? ['found' => false] : ['found' => true, ...$dashboard];
    }
}
