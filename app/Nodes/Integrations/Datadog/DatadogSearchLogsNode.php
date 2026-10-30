<?php

namespace App\Nodes\Integrations\Datadog;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class DatadogSearchLogsNode extends AbstractDatadogNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'datadog_search_logs';
    }

    public function name(): string
    {
        return 'Datadog: Search Logs';
    }

    public function description(): string
    {
        return 'Searches logs by query and time range. As a trigger it starts the flow for each new matching log. Checked every few minutes.';
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
                ...$this->searchFields('Datadog log search.'),
                'indexes' => Field::advanced(Field::list('Indexes', 'Default every index.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $indexes = $this->listOf($config, 'indexes');
        $result = $this->datadog($run, $config, 'POST', '/api/v2/logs/events/search', [], $this->searchBody($config, $indexes === [] ? [] : ['indexes' => $indexes]));
        $logs = array_values(array_filter((array) ($result['data'] ?? []), 'is_array'));

        return ['logs' => array_map($this->shapeLog(...), $logs), 'count' => count($logs), 'next_cursor' => data_get($result, 'meta.page.after')];
    }

    public function triggerName(): string
    {
        return 'Datadog: New log';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a log matching the query arrives in Datadog. Checked every few minutes; keep the query narrow.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'logs';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100, 'cursor' => null, 'from' => 'now-15m', 'to' => 'now', 'sort' => '-timestamp'];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
