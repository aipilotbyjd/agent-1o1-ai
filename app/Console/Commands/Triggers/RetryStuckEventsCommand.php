<?php

namespace App\Console\Commands\Triggers;

use App\Enums\Triggers\TriggerEventStatus;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Models\Triggers\TriggerEvent;
use Illuminate\Console\Command;

/**
 * Recovers events left `queued`/`running` past their grace period — the
 * durability net for a lost dispatch, a flushed queue, or a killed worker.
 * See docs/TRIGGERS_PLAN.md's "The one rule that makes it lossless".
 */
class RetryStuckEventsCommand extends Command
{
    protected $signature = 'triggers:retry-stuck';

    protected $description = 'Re-queue trigger events stranded in queued/running past their grace period.';

    public function handle(): int
    {
        $queuedBefore = now()->subMinutes((int) config('triggers.stuck.queued_after_minutes'));
        $runningBefore = now()->subMinutes((int) config('triggers.stuck.running_after_minutes'));

        TriggerEvent::query()
            ->with('trigger')
            ->whereIn('status', TriggerEventStatus::unresolved())
            ->where(fn ($query) => $query
                ->where(fn ($inner) => $inner->where('status', TriggerEventStatus::Queued)->where('created_at', '<', $queuedBefore))
                ->orWhere(fn ($inner) => $inner->where('status', TriggerEventStatus::Running)->where('updated_at', '<', $runningBefore)))
            ->oldest()
            ->limit((int) config('triggers.stuck.batch_size'))
            ->get()
            ->each(function (TriggerEvent $event): void {
                $trigger = $event->trigger;

                if ($trigger === null || ! $trigger->is_active) {
                    $event->finish(TriggerEventStatus::Skipped, 'Trigger unavailable at retry time.');

                    return;
                }

                // Back to queued first — claim() only accepts non-terminal
                // events, and a stale `running` row looks identical to a live one.
                $event->requeue();

                FireTriggerEvent::dispatch($trigger, $event);
            });

        return self::SUCCESS;
    }
}
