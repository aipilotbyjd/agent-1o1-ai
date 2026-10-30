<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListNotebooksNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_notebooks';
    }

    public function name(): string
    {
        return 'Datadog: List Notebooks';
    }

    public function description(): string
    {
        return 'Lists notebooks, optionally searching their names.';
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
                'author_handle' => Field::text('Author', 'An email handle.'),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
                'start' => Field::advanced(Field::integer('Skip', 'How many to skip.', 0, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->datadog($run, $config, 'GET', '/api/v1/notebooks', ['query' => $this->text($config, 'query'), 'author_handle' => $this->text($config, 'author_handle'), 'count' => $this->limit($config, 100), 'start' => (int) ($config['start'] ?? 0)]);
        $notebooks = $this->records($result);

        return ['notebooks' => $notebooks, 'count' => count($notebooks), 'total' => data_get($result, 'meta.page.total_count')];
    }
}
