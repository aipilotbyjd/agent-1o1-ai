<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryDeleteDashboardNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_delete_dashboard';
    }

    public function name(): string
    {
        return 'Sentry: Delete Dashboard';
    }

    public function description(): string
    {
        return 'Deletes a custom dashboard.';
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
                'organization' => $this->organizationField(),
                'dashboard_id' => Field::text('Dashboard id', 'From List Dashboards.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'dashboard_id', 'The dashboard id');
        $this->sentry($run, $config, 'DELETE', '/api/0/organizations/'.$this->org($run, $config)."/dashboards/{$id}/");

        return ['deleted' => true, 'id' => $id];
    }
}
