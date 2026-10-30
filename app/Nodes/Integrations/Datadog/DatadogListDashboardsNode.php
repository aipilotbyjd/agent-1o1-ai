<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListDashboardsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_dashboards';
    }

    public function name(): string
    {
        return 'Datadog: List Dashboards';
    }

    public function description(): string
    {
        return 'Lists dashboards; optionally only those whose title contains some text.';
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
                'search' => Field::text('Title contains', 'Looks through the page read.'),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
                'shared' => Field::advanced(Field::boolean('Only shared dashboards')),
                'start' => Field::advanced(Field::integer('Skip', 'How many to skip.', 0, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->datadog($run, $config, 'GET', '/api/v1/dashboard', [
            'filter[shared]' => ($config['shared'] ?? false) ? 'true' : null,
            'count' => $this->limit($config, 100),
            'start' => (int) ($config['start'] ?? 0),
        ]);
        $dashboards = array_values(array_filter((array) ($result['dashboards'] ?? []), 'is_array'));
        $search = mb_strtolower((string) $this->text($config, 'search'));

        if ($search !== '') {
            $dashboards = array_values(array_filter($dashboards, fn (array $dashboard): bool => str_contains(mb_strtolower((string) ($dashboard['title'] ?? '')), $search)));
        }

        return ['dashboards' => $dashboards, 'count' => count($dashboards)];
    }
}
