<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogCreateDashboardListNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_create_dashboard_list';
    }

    public function name(): string
    {
        return 'Datadog: Create Dashboard List';
    }

    public function description(): string
    {
        return 'Creates a dashboard list.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->datadog($run, $config, 'POST', '/api/v1/dashboard/lists/manual', [], ['name' => $this->requiredString($config, 'name', 'The name')]);
    }
}
