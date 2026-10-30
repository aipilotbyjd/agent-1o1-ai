<?php

namespace App\Nodes\Integrations\Datadog;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * The monitors a poll looks at, each with an id that changes when its state does:
 * the status plus when it last triggered, so Alert to OK to Alert fires three times.
 * Monitor search has `last_triggered_ts`; one chosen monitor is read directly, with
 * the times of its groups.
 *
 * Datadog only tells a webhook about a monitor whose message mentions it. With
 * "Instant delivery" on, `DatadogSource` makes a webhook and adds that mention to the
 * chosen monitor's message, and takes it out when the trigger goes away.
 */
abstract class AbstractDatadogMonitorChangeNode extends AbstractDatadogNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * The states the node keeps, or null for all.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>|null
     */
    abstract protected function statesToKeep(array $config): ?array;

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
                'monitor_id' => $this->monitorField('Only this monitor'),
                'query' => Field::text('Or monitors matching', 'Space-separated attributes, as on the Manage Monitors page.', 'tag:team:payments type:metric'),
                ...$this->extraFields(),
                'instant_delivery' => Field::advanced(Field::boolean('Instant delivery', 'For one chosen monitor: adds a webhook mention to its message, so Datadog tells this flow at once. The mention is removed when the trigger is. Needs the monitors_write and create_webhooks permissions; otherwise it keeps checking every few minutes.')),
                'limit' => Field::integer('Max monitors', null, 200, 1, 1000),
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function extraFields(): array
    {
        return [];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $monitors = filled($config['monitor_id'] ?? null) ? [$this->readOne($run, $config)] : $this->readMany($run, $config);
        $states = $this->statesToKeep($config);

        if ($states !== null) {
            $monitors = array_values(array_filter($monitors, fn (array $monitor): bool => in_array($monitor['status'], $states, true)));
        }

        return ['monitors' => $monitors, 'count' => count($monitors)];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function readOne(Run $run, array $config): array
    {
        $monitor = $this->datadog($run, $config, 'GET', '/api/v1/monitor/'.$this->numericId($config, 'monitor_id', 'The monitor'), ['group_states' => 'all']);
        $groups = array_filter((array) data_get($monitor, 'state.groups', []), 'is_array');
        $triggered = max([0, ...array_map(fn (array $group): int => (int) ($group['last_triggered_ts'] ?? 0), $groups)]);
        $resolved = max([0, ...array_map(fn (array $group): int => (int) ($group['last_resolved_ts'] ?? 0), $groups)]);
        $shaped = $this->shapeMonitor([...$monitor, 'last_triggered_ts' => $triggered ?: null]);

        return [...$shaped, 'change_id' => "{$shaped['id']}:{$shaped['status']}:{$triggered}:{$resolved}"];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    private function readMany(Run $run, array $config): array
    {
        $result = $this->datadog($run, $config, 'GET', '/api/v1/monitor/search', ['query' => $this->text($config, 'query'), 'per_page' => $this->limit($config, 200), 'page' => 0]);

        return array_map(function (array $monitor): array {
            $shaped = $this->shapeMonitor($monitor);

            return [...$shaped, 'change_id' => $shaped['id'].':'.$shaped['status'].':'.($monitor['last_triggered_ts'] ?? 0)];
        }, array_values(array_filter((array) ($result['monitors'] ?? []), 'is_array')));
    }

    protected function triggerItemsPath(): ?string
    {
        return 'monitors';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['change_id'] ?? '');
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 1000];
    }
}
