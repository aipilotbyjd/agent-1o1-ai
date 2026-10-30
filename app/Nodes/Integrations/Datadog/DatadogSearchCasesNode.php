<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogSearchCasesNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_search_cases';
    }

    public function name(): string
    {
        return 'Datadog: Search Cases';
    }

    public function description(): string
    {
        return 'Searches the work items of Case Management.';
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
                'query' => Field::text('Query', 'Text or attributes to look for.'),
                'limit' => Field::integer('Max results', null, 50, 1, 100),
                'sort_field' => Field::advanced(Field::select('Sort by', ['created_at' => 'Created', 'priority' => 'Priority', 'status' => 'Status'])),
                'page' => Field::advanced(Field::integer('Page', 'Starting at 0.', 0, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->datadog($run, $config, 'GET', '/api/v2/cases', ['filter' => $this->text($config, 'query'), 'sort[field]' => $this->text($config, 'sort_field'), 'page[size]' => $this->limit($config, 50, 100), 'page[number]' => (int) ($config['page'] ?? 0)]);
        $cases = $this->records($result);

        return ['cases' => $cases, 'count' => count($cases), 'total' => data_get($result, 'meta.page.total')];
    }
}
