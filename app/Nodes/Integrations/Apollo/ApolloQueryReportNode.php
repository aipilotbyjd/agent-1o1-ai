<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloQueryReportNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_query_report';
    }

    public function name(): string
    {
        return 'Apollo: Query Analytics Report';
    }

    public function description(): string
    {
        return 'Runs an analytics report over sequences, emails, calls and more. See Apollo\'s metrics reference for valid metric names.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['metrics', 'date_ranges'],
            'properties' => [
                ...$this->credentialFields(),
                'metrics' => Field::json('Metrics', 'Each with value, smart_datetime_reference and smart_user_id_reference, as in Apollo\'s reference.', 'array'),
                'date_ranges' => Field::json('Date range', 'One object with a modality preset, as in Apollo\'s reference.', 'array'),
                'group_by' => Field::json('Group by', 'At most one object with name and limit.', 'array'),
                'sorts' => Field::advanced(Field::json('Sort', 'At most one object with asc and metric.', 'array')),
                'filters' => Field::advanced(Field::json('Filters')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = ['metrics' => $this->records($config, 'metrics', 'The metrics'), 'group_by' => array_values((array) ($config['group_by'] ?? [])), 'pivot_group_by' => [], 'sorts' => array_values((array) ($config['sorts'] ?? [])), 'filters' => (object) ($config['filters'] ?? []), 'group_by_totals_selected' => false, 'pivot_group_by_totals_selected' => false, 'date_ranges' => $this->records($config, 'date_ranges', 'The date range')];

        return $this->apollo($run, $config, 'POST', '/reports/sync_report', [], $body);
    }
}
