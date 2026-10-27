<?php

namespace App\Jobs\Triggers;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Triggers\TriggerType;
use App\Exceptions\ConnectorException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Services\Connectors\ConnectorTokens;
use App\Services\Http\GuardedHttp;
use App\Services\Triggers\TriggerService;
use App\Services\Workflows\NodeRegistry;
use Closure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Checks a polling trigger once and starts one run per new item.
 *
 * Items come from either:
 * - a plain endpoint, described by the trigger's config (`url`, `method`,
 *   `items_path`, `id_path`, `cursor_param`, `cursor_path`), sent with the
 *   connected account's token as a Bearer header when one is set; or
 * - a reader node switched on as a flow trigger (`config.node_type`), whose
 *   own execute() does the reading.
 *
 * The first poll only takes a baseline: what's already there is recorded as
 * seen, not run, so switching a trigger on doesn't start a run per existing
 * item. At most `triggers.poll_max_items` items are looked at per poll.
 */
class PollTrigger implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Seconds a poll holds its trigger's lock if the worker dies mid-poll
     * (`triggers.poll_lock_seconds`).
     */
    public int $uniqueFor;

    /**
     * Queue a poll of one trigger on the polling queue.
     */
    public function __construct(public Trigger $trigger)
    {
        $this->uniqueFor = (int) config('triggers.poll_lock_seconds');
        $this->onQueue((string) config('triggers.poll_queue'));
    }

    /**
     * One poll per trigger at a time.
     *
     * `last_run_at` only moves when a poll finishes, so without this a slow
     * poll would be queued again on every scheduler tick, and the copies
     * would race on the cursor.
     */
    public function uniqueId(): string
    {
        return (string) $this->trigger->getKey();
    }

    /**
     * Fetch the items, skip the ones already seen, and hand each new one to
     * intake. A failed fetch counts toward the circuit breaker.
     */
    public function handle(TriggerService $triggers, GuardedHttp $http, ConnectorTokens $tokens, NodeRegistry $registry): void
    {
        $this->trigger->refresh();

        if (! $this->trigger->is_active || $this->trigger->workspace->isSuspended()) {
            return;
        }

        $config = array_merge((array) ($this->trigger->preset?->config ?? []), (array) $this->trigger->config);

        try {
            [$items, $idOf, $expand, $cursorValue] = isset($config['node_type'])
                ? $this->readerItems($registry, $config)
                : $this->urlItems($http, $tokens, $config);
        } catch (Throwable $exception) {
            $this->recordReadError($triggers, $exception);
            $this->trigger->forceFill(['last_run_at' => now()])->save();

            return;
        }

        Cache::forget($this->transientStreakKey());

        $items = array_slice($items, 0, max(1, (int) config('triggers.poll_max_items')));

        // `poll_cursor` is set after every poll, even an empty one, so only
        // the very first successful poll takes the baseline.
        $isBaseline = $this->trigger->poll_cursor === null && ! $this->trigger->events()->exists();

        foreach ($items as $item) {
            $deliveryId = $idOf($item);

            if ($isBaseline) {
                $triggers->recordBaseline($this->trigger, $item, $deliveryId);

                continue;
            }

            // Skip known items before expanding: expanding can cost an API call
            // per item, and almost every item in a poll is one already seen.
            if ($this->trigger->events()->where('delivery_id', $deliveryId)->exists()) {
                continue;
            }

            try {
                $payload = $expand($item);
            } catch (Throwable $exception) {
                $this->recordReadError($triggers, $exception);

                continue;
            }

            $triggers->receive($this->trigger, TriggerType::Polling, $payload, $deliveryId);
        }

        // forceFill: both are system bookkeeping, deliberately not fillable,
        // so update() would silently drop them.
        $this->trigger->forceFill([
            'poll_cursor' => ['value' => $items === [] ? ($this->trigger->poll_cursor['value'] ?? null) : $cursorValue(end($items))],
            'last_run_at' => now(),
        ])->save();
    }

    /**
     * Read items from a plain endpoint.
     *
     * Returns the items, plus how to get each one's id, payload and cursor
     * value.
     *
     * @param  array<string, mixed>  $config
     * @return array{0: list<array<string, mixed>>, 1: Closure(array<string, mixed>): string, 2: Closure(array<string, mixed>): array<string, mixed>, 3: Closure(array<string, mixed>): mixed}
     */
    private function urlItems(GuardedHttp $http, ConnectorTokens $tokens, array $config): array
    {
        $url = $config['url'] ?? throw new RuntimeException("Polling trigger {$this->trigger->id} has no URL.");

        $response = $http->send(strtolower((string) ($config['method'] ?? 'get')), $url, headers: $this->authHeaders($tokens), query: $this->cursorQuery($config));
        $response->throw();

        $items = array_values(array_filter((array) (data_get($response->json(), $config['items_path'] ?? null) ?? []), 'is_array'));

        // Runs start oldest first, so an endpoint that lists the newest item
        // first is turned around.
        if (($config['newest_first'] ?? false) === true) {
            $items = array_reverse($items);
        }

        $idPath = $config['id_path'] ?? 'id';

        return [
            $items,
            fn (array $item): string => (string) data_get($item, $idPath),
            fn (array $item): array => $item,
            fn (array $item): mixed => data_get($item, $config['cursor_path'] ?? $idPath),
        ];
    }

    /**
     * Read items through a reader node switched on as a flow trigger.
     *
     * The node's own execute() does the reading, as the trigger's owner,
     * through the node's connected account. Returns the same shape as
     * urlItems().
     *
     * @param  array<string, mixed>  $config
     * @return array{0: list<array<string, mixed>>, 1: Closure(array<string, mixed>): string, 2: Closure(array<string, mixed>): array<string, mixed>, 3: Closure(array<string, mixed>): mixed}
     */
    private function readerItems(NodeRegistry $registry, array $config): array
    {
        $node = $registry->resolve((string) $config['node_type']);

        if (! $node instanceof SupportsTriggerMode) {
            throw new RuntimeException("Node type [{$config['node_type']}] can't act as a trigger.");
        }

        $nodeConfig = (array) ($config['node_config'] ?? []);

        if ($this->trigger->credential_id !== null) {
            $nodeConfig['credential_id'] = $this->trigger->credential_id;
        }

        // Never saved: it only tells the node which workspace and person it
        // acts for, which is all a real run gives ResolvesConnectorCredential.
        $run = new Run(['workspace_id' => $this->trigger->workspace_id, 'triggered_by' => $this->trigger->actingUser()?->id]);

        $items = $node->triggerItems($node->execute($run, $node->triggerReadConfig($nodeConfig), []), $nodeConfig);

        // Runs start oldest first, so a burst is processed in the order it
        // happened.
        if ($node->triggerListsNewestFirst()) {
            $items = array_reverse($items);
        }

        return [
            $items,
            fn (array $item): string => $node->triggerItemId($item),
            fn (array $item): array => $node->expandTriggerItem($run, $nodeConfig, $item),
            fn (array $item): mixed => $node->triggerItemId($item),
        ];
    }

    /**
     * Count a failed read or expand toward the circuit breaker, unless the
     * source is only having a bad moment.
     *
     * A rate limit, a 5xx or a dropped connection says nothing about the
     * trigger itself, and a one-minute poll would otherwise switch off for
     * good after three minutes of someone else's outage. Those are logged and
     * only count once they have gone on for `triggers.poll_transient_failures_
     * before_count` polls in a row. Anything else (an expired account, a bad
     * URL, a 4xx) counts at once.
     */
    private function recordReadError(TriggerService $triggers, Throwable $exception): void
    {
        if (! $this->isTransient($exception)) {
            Cache::forget($this->transientStreakKey());
            $triggers->recordFailure($this->trigger);

            return;
        }

        Cache::add($this->transientStreakKey(), 0, now()->addDay());
        $streak = (int) Cache::increment($this->transientStreakKey());

        Log::info('Polling trigger hit a temporary error.', ['trigger_id' => $this->trigger->id, 'streak' => $streak, 'error' => $exception->getMessage()]);

        if ($streak >= max(1, (int) config('triggers.poll_transient_failures_before_count'))) {
            Cache::forget($this->transientStreakKey());
            $triggers->recordFailure($this->trigger);
        }
    }

    /**
     * Whether an error is likely to pass by itself: a dropped connection, a
     * timeout, a rate limit, or a server error. Apps that report errors in a
     * 200 body (Slack) are recognised by their error code.
     */
    private function isTransient(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            $status = $exception->response->status();

            return $status >= 500 || in_array($status, [408, 425, 429], true);
        }

        return (bool) preg_match('/ratelimited|rate[_ ]limit|timeout|timed out|temporar|unavailable|internal_error|fatal_error/i', $exception->getMessage());
    }

    /**
     * The cache key counting this trigger's polls in a row that hit a
     * temporary error.
     */
    private function transientStreakKey(): string
    {
        return "triggers:poll-transient:{$this->trigger->id}";
    }

    /**
     * The Authorization header for the trigger's connected account, if any.
     *
     * Only an account the trigger's owner could pick themselves: a team
     * account or their own personal one. TriggerNodeSync checks this at
     * publish; it's checked again here in case the account changed since.
     *
     * @return array<string, string>
     *
     * @throws ConnectorException when the account can't produce a token
     */
    private function authHeaders(ConnectorTokens $tokens): array
    {
        if ($this->trigger->credential_id === null) {
            return [];
        }

        $credential = ConnectorCredential::query()
            ->where('workspace_id', $this->trigger->workspace_id)
            ->find($this->trigger->credential_id);

        $owner = $this->trigger->actingUser();

        if ($credential === null || ($owner !== null && ! $credential->isVisibleTo($owner))) {
            throw new ConnectorException("Polling trigger {$this->trigger->id} has no usable account.");
        }

        return ['Authorization' => 'Bearer '.$tokens->accessToken($credential)];
    }

    /**
     * The query parameter that asks the endpoint for items after the last
     * one seen, when the trigger sets `cursor_param`.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function cursorQuery(array $config): array
    {
        $cursorParam = $config['cursor_param'] ?? null;
        $cursorValue = $this->trigger->poll_cursor['value'] ?? null;

        if ($cursorParam === null || $cursorValue === null) {
            return [];
        }

        return [$cursorParam => $cursorValue];
    }
}
