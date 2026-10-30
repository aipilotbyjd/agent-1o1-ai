<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogSearchErrorIssuesNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_search_error_issues';
    }

    public function name(): string
    {
        return 'Datadog: Search Error Tracking Issues';
    }

    public function description(): string
    {
        return 'Searches the issues of Datadog Error Tracking.';
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
                'query' => Field::text('Query', null, 'service:web'),
                'from' => Field::text('From', 'Default now-1d.', 'now-1d'),
                'to' => Field::text('To', 'Default now.', 'now'),
                'track' => Field::select('Source', ['trace' => 'Traces', 'logs' => 'Logs', 'rum' => 'RUM'], null, 'trace'),
                'order_by' => Field::advanced(Field::select('Order', ['TOTAL_COUNT' => 'Total count', 'FIRST_SEEN' => 'First seen', 'IMPACTED_SESSIONS' => 'Impacted sessions', 'PRIORITY' => 'Priority'])),
                'states' => Field::advanced(Field::list('States', 'OPEN, ACKNOWLEDGED, RESOLVED, IGNORED, EXCLUDED.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $attributes = array_filter([
            'query' => $this->text($config, 'query') ?? '*',
            'from' => $this->unixTime($config['from'] ?? 'now-1d', 'From') * 1000,
            'to' => $this->unixTime($config['to'] ?? 'now', 'To') * 1000,
            'track' => $this->text($config, 'track') ?? 'trace',
            'order_by' => $this->text($config, 'order_by'),
            'states' => $this->listOf($config, 'states') ?: null,
        ], fn (mixed $value): bool => $value !== null);
        $result = $this->datadog($run, $config, 'POST', '/api/v2/error-tracking/issues/search', ['include' => 'issue'], ['data' => ['type' => 'search_request', 'attributes' => $attributes]]);
        $issues = $this->records(['data' => (array) ($result['included'] ?? $result['data'] ?? [])]);

        return ['issues' => $issues, 'count' => count($issues)];
    }
}
