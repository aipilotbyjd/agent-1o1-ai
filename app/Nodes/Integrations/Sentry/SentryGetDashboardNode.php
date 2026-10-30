<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryGetDashboardNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_get_dashboard';
    }

    public function name(): string
    {
        return 'Sentry: Get Dashboard';
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
                'organization' => $this->organizationField(),
                'dashboard_id' => Field::text('Dashboard id', 'From List Dashboards.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $dashboard = $this->find($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/dashboards/'.$this->pathId($config, 'dashboard_id', 'The dashboard id').'/');

        return $dashboard === null ? ['found' => false] : ['found' => true, ...$dashboard];
    }
}
