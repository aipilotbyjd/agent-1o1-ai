<?php

namespace App\Console\Commands\Triggers;

use App\Enums\Triggers\TriggerType;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Triggers\Trigger;
use App\Services\Triggers\TriggerService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * The every-minute tick: queues an event for each due schedule and a poll for
 * each due polling trigger.
 *
 * It starts no runs itself, so the tick costs about the same whether one
 * trigger is due or a thousand.
 */
class RunDueTriggersCommand extends Command
{
    protected $signature = 'triggers:run-due';

    protected $description = 'Queue an event for every due schedule trigger and dispatch a poll for every due polling trigger.';

    /**
     * Queue everything that's due and report how much.
     */
    public function handle(TriggerService $triggers): int
    {
        $queued = $this->queueDueSchedules($triggers) + $this->queueDuePolls();

        $this->info("Queued {$queued} trigger event(s).");

        return self::SUCCESS;
    }

    /**
     * Fire every schedule whose `next_run_at` has arrived, and move it to its
     * next slot.
     *
     * A slot missed while the scheduler was down fires once if it's within
     * `triggers.schedule_catch_up_minutes`, and is skipped if it's older.
     * Either way it fires at most once, however many slots were missed.
     */
    private function queueDueSchedules(TriggerService $triggers): int
    {
        $now = now();
        $catchUpCutoff = $now->copy()->subMinutes(max(0, (int) config('triggers.schedule_catch_up_minutes')));
        $queued = 0;

        // Schedules saved before `next_run_at` existed get one now. One with an
        // invalid cron keeps none and is never due.
        Trigger::query()
            ->where('type', TriggerType::Schedule)
            ->where('is_active', true)
            ->whereNull('next_run_at')
            ->each(function (Trigger $trigger): void {
                if (Trigger::nextRunFor($trigger->config ?? [], now(), allowCurrentMinute: true) === null) {
                    Log::warning('Schedule trigger skipped: invalid cron expression.', ['trigger_id' => $trigger->id]);

                    return;
                }

                $trigger->save();
            });

        Trigger::query()
            ->where('type', TriggerType::Schedule)
            ->where('is_active', true)
            ->where('next_run_at', '<=', $now)
            ->orderBy('next_run_at')
            ->each(function (Trigger $trigger) use ($triggers, $now, $catchUpCutoff, &$queued): void {
                $slot = $trigger->next_run_at;

                // Move on first, so if intake throws, the next tick doesn't retry
                // the same slot forever.
                $trigger->forceFill(['next_run_at' => Trigger::nextRunFor($trigger->config ?? [], $now)])->save();

                if ($slot->lt($catchUpCutoff)) {
                    Log::info('Schedule trigger slot missed beyond the catch-up window.', ['trigger_id' => $trigger->id, 'slot' => $slot->toIso8601String()]);

                    return;
                }

                // The delivery id names the slot, so a second scheduler or a
                // re-run of this tick hits the unique index instead of firing
                // twice. Unlike a lock, that holds across servers.
                $triggers->receive(
                    $trigger,
                    TriggerType::Schedule,
                    ['scheduled_at' => $slot->toIso8601String()],
                    deliveryId: 'schedule:'.$slot->format('Y-m-d H:i'),
                );

                $queued++;
            });

        return $queued;
    }

    /**
     * Queue a poll for every polling trigger whose interval has passed since
     * its last poll.
     */
    private function queueDuePolls(): int
    {
        $queued = 0;

        Trigger::query()
            ->where('type', TriggerType::Polling)
            ->where('is_active', true)
            ->each(function (Trigger $trigger) use (&$queued): void {
                $intervalMinutes = max(
                    (int) config('triggers.min_poll_interval_minutes'),
                    (int) ($trigger->config['poll_interval_minutes'] ?? config('triggers.poll_every_minutes')),
                );

                if ($trigger->last_run_at instanceof Carbon && $trigger->last_run_at->addMinutes($intervalMinutes)->isFuture()) {
                    return;
                }

                PollTrigger::dispatch($trigger);
                $queued++;
            });

        return $queued;
    }
}
