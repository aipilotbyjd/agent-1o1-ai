<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Contracts\KeepsTriggerState;
use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * The team's latest jobs of one kind (crawls, batch scrapes, extracts), as a reader
 * and as a "job finished" trigger.
 *
 * Firecrawl posts a job's events only to the address given when that job starts, and
 * has no account-wide webhook, so this trigger polls. The activity feed names the
 * jobs but not how they ended, and a status answer can be several megabytes, so the
 * trigger remembers which jobs are still running and only asks about those.
 */
abstract class AbstractFirecrawlJobFeedNode extends AbstractFirecrawlNode implements KeepsTriggerState, SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int STATUS_CHECKS_PER_POLL = 10;

    private const int SEEN_KEPT = 300;

    /**
     * The job kind in the activity feed.
     */
    abstract protected function endpoint(): string;

    /**
     * The API path of a job's status.
     */
    abstract protected function statusPath(string $id): string;

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
                'outcome' => Field::select('Outcome', ['any' => 'Any', 'completed' => 'Completed', 'failed' => 'Failed', 'cancelled' => 'Cancelled'], 'As a trigger, the endings that start the flow.', 'any'),
                'limit' => Field::integer('Max results', null, 25, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $jobs = $this->activity($run, $config, $this->limit($config, 25, 100));

        return ['jobs' => $jobs, 'count' => count($jobs)];
    }

    /**
     * Not used: finished jobs come from pollSince().
     */
    public function triggerItems(array $output, array $config): array
    {
        return [];
    }

    /**
     * The jobs that ended since the last poll.
     */
    public function pollSince(Run $run, array $config, ?array $state): array
    {
        $jobs = $this->activity($run, $config, 100);
        $ids = array_column($jobs, 'id');

        if ($state === null) {
            return ['items' => [], 'state' => ['seen' => array_slice($ids, 0, self::SEEN_KEPT), 'waiting' => []]];
        }

        $seen = (array) ($state['seen'] ?? []);
        $waiting = (array) ($state['waiting'] ?? []);

        foreach ($jobs as $job) {
            if (! in_array($job['id'], $seen, true) && ! isset($waiting[$job['id']])) {
                $waiting[$job['id']] = $job;
            }
        }

        $items = [];

        foreach (array_slice($waiting, 0, self::STATUS_CHECKS_PER_POLL, true) as $id => $job) {
            $status = $this->firecrawl($run, $config, 'GET', $this->statusPath((string) $id), timeout: 15);

            if (! in_array($status['status'] ?? null, ['completed', 'failed', 'cancelled'], true)) {
                continue;
            }

            unset($waiting[$id]);

            if (in_array($config['outcome'] ?? 'any', ['any', '', $status['status']], true)) {
                $items[] = [...$job, 'status' => $status['status'], 'total' => $status['total'] ?? null, 'completed' => $status['completed'] ?? null, 'credits_used' => $status['creditsUsed'] ?? null, 'tokens_used' => $status['tokensUsed'] ?? null];
            }
        }

        return ['items' => $items, 'state' => ['seen' => array_slice(array_values(array_unique([...$ids, ...$seen])), 0, self::SEEN_KEPT), 'waiting' => $waiting]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function triggerSampleItems(Run $run, array $config): array
    {
        return [['id' => '019df960-5f2a-75fb-a98b-bd2d32ca67d4', 'kind' => $this->endpoint(), 'target' => 'https://example.com', 'created_at' => '2026-01-31T09:00:00Z', 'status' => 'completed', 'total' => 10, 'completed' => 10, 'credits_used' => 10, 'tokens_used' => null]];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'jobs';
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    /**
     * The team's latest jobs of this kind.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    private function activity(Run $run, array $config, int $limit): array
    {
        $rows = array_filter((array) ($this->firecrawl($run, $config, 'GET', '/team/activity', query: ['endpoint' => $this->endpoint(), 'limit' => $limit])['data'] ?? []), fn (mixed $row): bool => is_array($row) && filled($row['id'] ?? null));

        return array_map(fn (array $row): array => ['id' => (string) $row['id'], 'kind' => $row['endpoint'] ?? $this->endpoint(), 'target' => $row['target'] ?? null, 'created_at' => $row['created_at'] ?? null], array_values($rows));
    }
}
