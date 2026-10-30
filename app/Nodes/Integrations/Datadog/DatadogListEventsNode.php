<?php

namespace App\Nodes\Integrations\Datadog;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class DatadogListEventsNode extends AbstractDatadogNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'datadog_list_events';
    }

    public function name(): string
    {
        return 'Datadog: List Events';
    }

    public function description(): string
    {
        return 'Lists events in a time window, newest first. As a trigger it starts the flow for each new one. Checked every few minutes.';
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
                'query' => Field::text('Query', 'Datadog event search.', 'source:deploy status:error'),
                'from' => Field::text('From', 'Default now-1h. A date, or relative like now-1d.', 'now-1h'),
                'to' => Field::text('To', 'Default now.', 'now'),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
                'cursor' => Field::advanced(Field::text('Page cursor', 'The "next_cursor" of the last call, for the next page.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->datadog($run, $config, 'GET', '/api/v2/events', [
            'filter[query]' => $this->text($config, 'query'),
            'filter[from]' => $this->text($config, 'from') ?? 'now-1h',
            'filter[to]' => $this->text($config, 'to') ?? 'now',
            'sort' => '-timestamp',
            'page[limit]' => $this->limit($config),
            'page[cursor]' => $this->text($config, 'cursor'),
        ]);
        $events = array_values(array_filter((array) ($result['data'] ?? []), 'is_array'));

        return ['events' => array_map($this->shapeEvent(...), $events), 'count' => count($events), 'next_cursor' => data_get($result, 'meta.page.after')];
    }

    public function triggerName(): string
    {
        return 'Datadog: New event';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an event matching the query is posted in Datadog. Checked every few minutes.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'events';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100, 'cursor' => null, 'from' => 'now-1h', 'to' => 'now'];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
