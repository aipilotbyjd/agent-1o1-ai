<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogSearchMonitorGroupsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_search_monitor_groups';
    }

    public function name(): string
    {
        return 'Datadog: Search Monitor Groups';
    }

    public function description(): string
    {
        return 'Searches the groups of multi-alert monitors and returns the status of each.';
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
                'query' => Field::text('Query', 'Space-separated attributes.', 'status:alert'),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
                'page' => Field::advanced(Field::integer('Page', 'Starting at 0.', 0, 0)),
                'sort' => Field::advanced(Field::text('Sort', 'Like status,desc.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->datadog($run, $config, 'GET', '/api/v1/monitor/groups/search', [
            'query' => $this->text($config, 'query'),
            'page' => (int) ($config['page'] ?? 0),
            'per_page' => $this->limit($config),
            'sort' => $this->text($config, 'sort'),
        ]);
        $groups = array_values(array_filter((array) ($result['groups'] ?? []), 'is_array'));

        return ['groups' => $groups, 'count' => count($groups), 'total' => data_get($result, 'metadata.total_count')];
    }
}
