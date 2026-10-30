<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListSlosNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_slos';
    }

    public function name(): string
    {
        return 'Datadog: List SLOs';
    }

    public function description(): string
    {
        return 'Lists service level objectives.';
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
                'query' => Field::text('Name contains'),
                'tags_query' => Field::text('Tags', 'Like env:prod.'),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
                'metrics_query' => Field::advanced(Field::text('Metric', 'SLOs using this metric.')),
                'ids' => Field::advanced(Field::text('Ids', 'Comma-separated.')),
                'offset' => Field::advanced(Field::integer('Skip', 'How many to skip.', 0, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $slos = array_values(array_filter((array) ($this->datadog($run, $config, 'GET', '/api/v1/slo', ['query' => $this->text($config, 'query'), 'tags_query' => $this->text($config, 'tags_query'), 'metrics_query' => $this->text($config, 'metrics_query'), 'ids' => $this->text($config, 'ids'), 'limit' => $this->limit($config), 'offset' => (int) ($config['offset'] ?? 0)])['data'] ?? []), 'is_array'));

        return ['slos' => $slos, 'count' => count($slos)];
    }
}
