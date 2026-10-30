<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListDashboardListItemsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_dashboard_list_items';
    }

    public function name(): string
    {
        return 'Datadog: List Dashboards In List';
    }

    public function description(): string
    {
        return 'Lists the dashboards in a dashboard list.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        $items = array_values((array) ($this->datadog($run, $config, 'GET', "/api/v2/dashboard/lists/manual/{$id}/dashboards")['dashboards'] ?? []));

        return ['dashboards' => $items, 'count' => count($items)];
    }
}
