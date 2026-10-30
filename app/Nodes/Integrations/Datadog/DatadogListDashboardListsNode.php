<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DatadogListDashboardListsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_dashboard_lists';
    }

    public function name(): string
    {
        return 'Datadog: List Dashboard Lists';
    }

    public function description(): string
    {
        return 'Lists the dashboard lists.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),

            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $lists = array_values((array) ($this->datadog($run, $config, 'GET', '/api/v1/dashboard/lists/manual')['dashboard_lists'] ?? []));

        return ['lists' => $lists, 'count' => count($lists)];
    }
}
