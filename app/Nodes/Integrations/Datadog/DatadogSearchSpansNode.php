<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DatadogSearchSpansNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_search_spans';
    }

    public function name(): string
    {
        return 'Datadog: Search Spans';
    }

    public function description(): string
    {
        return 'Searches APM spans by query and time range.';
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
                ...$this->searchFields('Datadog span search, like service:web @http.status_code:500.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->searchBody($config);
        $attributes = ['filter' => $body['filter'], 'page' => $body['page'], 'sort' => $body['sort']];
        $result = $this->datadog($run, $config, 'POST', '/api/v2/spans/events/search', [], ['data' => ['type' => 'search_request', 'attributes' => $attributes]]);
        $spans = $this->records($result);

        return ['spans' => $spans, 'count' => count($spans), 'next_cursor' => data_get($result, 'meta.page.after')];
    }
}
