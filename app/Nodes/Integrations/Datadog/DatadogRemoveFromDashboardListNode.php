<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogRemoveFromDashboardListNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_remove_from_dashboard_list';
    }

    public function name(): string
    {
        return 'Datadog: Remove Dashboards From List';
    }

    public function description(): string
    {
        return 'Takes dashboards out of a dashboard list.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id', 'dashboard_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => Field::text('List id'),
                'dashboard_ids' => Field::list('Dashboard ids'),
                'dashboard_type' => Field::select('Dashboard type', ['custom_timeboard' => 'Custom timeboard', 'custom_screenboard' => 'Custom screenboard', 'integration_timeboard' => 'Integration timeboard', 'integration_screenboard' => 'Integration screenboard', 'host_timeboard' => 'Host timeboard'], null, 'custom_timeboard'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $type = $this->text($config, 'dashboard_type') ?? 'custom_timeboard';
        $items = array_map(fn (string $dashboard): array => ['id' => $dashboard, 'type' => $type], $this->listOf($config, 'dashboard_ids'));

        if ($items === []) {
            throw new InvalidArgumentException('At least one dashboard id is required.');
        }

        $id = $this->numericId($config, 'list_id', 'The list id');
        $result = $this->datadog($run, $config, 'DELETE', "/api/v2/dashboard/lists/manual/{$id}/dashboards", [], ['dashboards' => $items]);

        return ['removed' => array_values((array) ($result['deleted_dashboards_from_list'] ?? []))];
    }
}
