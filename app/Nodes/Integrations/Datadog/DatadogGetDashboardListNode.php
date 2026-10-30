<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogGetDashboardListNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_dashboard_list';
    }

    public function name(): string
    {
        return 'Datadog: Get Dashboard List';
    }

    public function description(): string
    {
        return 'Gets a dashboard list.';
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
        $list = $this->find($run, $config, '/api/v1/dashboard/lists/manual/'.$this->numericId($config, 'list_id', 'The list id'));

        return $list === null ? ['found' => false] : ['found' => true, ...$list];
    }
}
