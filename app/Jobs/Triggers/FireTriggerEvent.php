<?php

namespace App\Jobs\Triggers;

use App\Enums\Triggers\TriggerEventStatus;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use App\Services\Triggers\TriggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class FireTriggerEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Exceptions, not attempts — `WithoutOverlapping` releases the job back
     * onto the queue on lock contention, and a release burns a `$tries`
     * attempt without the job ever having run. Counting exceptions instead
     * means lock contention alone can never exhaust the job's budget.
     */
    public int $maxExceptions;

    public int $timeout;

    public function __construct(
        public Trigger $trigger,
        public TriggerEvent $event,
    ) {
        $this->maxExceptions = (int) config('triggers.fire_max_exceptions');
        $this->timeout = (int) config('triggers.fire_timeout_seconds');
        $this->onQueue((string) config('triggers.fire_queue'));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->event->id))->releaseAfter(30)->expireAfter(300),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return (array) config('triggers.fire_backoff_seconds');
    }

    public function handle(TriggerService $triggers): void
    {
        $this->event->refresh();

        // Idempotent re-dispatch: a terminal event (already fired, already
        // failed) claims false and this becomes a no-op — without this check
        // the retry-stuck command could start a second run for an event that
        // already settled.
        if (! $this->event->claim()) {
            return;
        }

        $this->event->increment('attempts');

        $triggers->fire($this->trigger, $this->event);
    }

    public function failed(?Throwable $exception): void
    {
        $this->event->refresh();

        if ($this->event->status === TriggerEventStatus::Fired) {
            return;
        }

        $this->event->finish(TriggerEventStatus::Failed, $exception?->getMessage());

        // An event that expired unclaimed (attempts still 0) never reached the
        // target — that's queue contention, not a broken trigger, and must not
        // count against the circuit breaker alone.
        if ($this->event->attempts > 0) {
            app(TriggerService::class)->recordFailure($this->trigger);
        }
    }
}
