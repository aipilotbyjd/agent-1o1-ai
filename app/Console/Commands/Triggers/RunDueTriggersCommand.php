<?php

namespace App\Console\Commands\Triggers;

use App\Enums\Triggers\TriggerType;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Triggers\Trigger;
use App\Services\Triggers\TriggerService;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Writes a row per due trigger and returns — starts no runs itself, so the
 * every-minute tick costs the same whether one trigger is due or a thousand.
 */
class RunDueTriggersCommand extends Command
{
    protected $signature = 'triggers:run-due';

    protected $description = 'Queue an event for every due schedule trigger and dispatch a poll for every due polling trigger.';

    public function handle(TriggerService $triggers): int
    {
        $queued = $this->queueDueSchedules($triggers) + $this->queueDuePolls();

        $this->info("Queued {$queued} trigger event(s).");

        return self::SUCCESS;
    }

    private function queueDueSchedules(TriggerService $triggers): int
    {
        $now = now();
        $queued = 0;

        Trigger::query()
            ->where('type', TriggerType::Schedule)
            ->where('is_active', true)
            ->each(function (Trigger $trigger) use ($triggers, $now, &$queued): void {
                $cron = $trigger->config['cron'] ?? null;

                if ($cron === null || ! (new CronExpression($cron))->isDue($now)) {
                    return;
                }

                // Double-firing is prevented by the delivery id, not a lock —
                // it's derived from the minute the trigger is due for, so a
                // second invocation in the same minute collides on the unique
                // index and is recorded as a duplicate. Holds across
                // concurrent servers, which a per-process lock would not.
                $triggers->receive(
                    $trigger,
                    TriggerType::Schedule,
                    ['scheduled_at' => $now->toIso8601String()],
                    deliveryId: 'schedule:'.$now->format('Y-m-d H:i'),
                );

                $queued++;
            });

        return $queued;
    }

    private function queueDuePolls(): int
    {
        $queued = 0;

        Trigger::query()
            ->where('type', TriggerType::Polling)
            ->where('is_active', true)
            ->each(function (Trigger $trigger) use (&$queued): void {
                $intervalMinutes = (int) ($trigger->config['poll_interval_minutes'] ?? config('triggers.poll_every_minutes'));

                if ($trigger->last_run_at instanceof Carbon && $trigger->last_run_at->addMinutes($intervalMinutes)->isFuture()) {
                    return;
                }

                PollTrigger::dispatch($trigger);
                $queued++;
            });

        return $queued;
    }
}
