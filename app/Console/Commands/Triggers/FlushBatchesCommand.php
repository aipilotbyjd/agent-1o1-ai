<?php

namespace App\Console\Commands\Triggers;

use App\Enums\Triggers\TriggerEventStatus;
use App\Models\Triggers\Trigger;
use App\Services\Triggers\TriggerService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Runs the events a trigger has been holding as one, once its group window
 * (`config.batch_seconds`) has passed since the oldest of them arrived.
 *
 * A trigger that has stopped grouping flushes what it still holds straight
 * away, so nothing is left waiting.
 */
class FlushBatchesCommand extends Command
{
    protected $signature = 'triggers:flush-batches';

    protected $description = 'Run the events triggers have been holding as one run each.';

    /**
     * Flush every trigger whose window is over.
     */
    public function handle(TriggerService $triggers): int
    {
        $flushed = 0;

        Trigger::query()
            ->where('is_active', true)
            ->whereHas('events', fn ($events) => $events->where('status', TriggerEventStatus::Held->value))
            ->eachById(function (Trigger $trigger) use ($triggers, &$flushed): void {
                $oldest = $trigger->events()->where('status', TriggerEventStatus::Held->value)->min('created_at');
                $window = max(0, (int) ($trigger->config['batch_seconds'] ?? 0));

                if ($oldest === null || Carbon::parse($oldest)->gt(now()->subSeconds($window))) {
                    return;
                }

                if ($triggers->flushHeld($trigger) !== null) {
                    $flushed++;
                }
            });

        $this->info("Ran {$flushed} group(s) of held events.");

        return self::SUCCESS;
    }
}
