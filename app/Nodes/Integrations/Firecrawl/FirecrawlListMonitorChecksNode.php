<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * A monitor keeps one webhook, so an instant trigger claims it (see FirecrawlSource). A monitor that already posts somewhere else stays polled.
 */
class FirecrawlListMonitorChecksNode extends AbstractFirecrawlNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'firecrawl_list_monitor_checks';
    }

    public function name(): string
    {
        return 'Firecrawl: List Monitor Checks';
    }

    public function description(): string
    {
        return 'Lists a monitor\'s latest checks with how many pages changed. As a trigger it starts the flow when a check finishes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['monitor_id'],
            'properties' => [
                ...$this->credentialFields(),
                'monitor_id' => $this->monitorField(),
                'status' => Field::advanced(Field::select('Status', ['completed', 'partial', 'failed', 'running', 'queued'], 'Leave empty for all.')),
                'limit' => Field::integer('Max results', null, 25, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $checks = array_values(array_filter((array) ($this->firecrawl($run, $config, 'GET', '/monitor/'.$this->pathId($config, 'monitor_id', 'The monitor').'/checks', query: ['limit' => $this->limit($config, 25, 100), 'status' => $this->textOf($config, 'status')])['data'] ?? []), 'is_array'));

        return ['checks' => array_map(fn (array $check): array => ['id' => $check['id'] ?? null, 'status' => $check['status'] ?? null, 'done' => $this->checkIsDone($check), 'trigger' => $check['trigger'] ?? null, 'started_at' => $check['startedAt'] ?? null, 'finished_at' => $check['finishedAt'] ?? null, 'summary' => $check['summary'] ?? null, 'credits' => $check['actualCredits'] ?? null], $checks), 'count' => count($checks)];
    }

    public function triggerName(): string
    {
        return 'Firecrawl: Monitor check completed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a check of a Firecrawl monitor finishes. Instant where the monitor\'s webhook is free, otherwise checked every few minutes.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'checks';
    }

    public function triggerItems(array $output, array $config): array
    {
        return array_values(array_filter((array) ($output['checks'] ?? []), fn (mixed $check): bool => is_array($check) && ($check['done'] ?? false) === true));
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
