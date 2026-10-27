<?php

namespace App\Jobs\Triggers;

use App\Enums\Triggers\TriggerType;
use App\Models\Triggers\Trigger;
use App\Services\Triggers\PollReader;
use App\Services\Triggers\TriggerService;
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
    public function handle(TriggerService $triggers, PollReader $reader): void
    {
        $this->trigger->refresh();

        if (! $this->trigger->is_active || $this->trigger->workspace->isSuspended()) {
            return;
        }

        try {
            [$items, $idOf, $expand, $cursorValue] = $reader->read($this->trigger);
        } catch (Throwable $exception) {
            $this->recordReadError($triggers, $exception);
            $this->trigger->forceFill(['last_run_at' => now(), 'next_poll_at' => $this->trigger->nextPollTime()])->save();

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
            'next_poll_at' => $this->trigger->nextPollTime(),
        ])->save();
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

}
