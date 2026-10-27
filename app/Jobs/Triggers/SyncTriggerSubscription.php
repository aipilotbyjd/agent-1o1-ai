<?php

namespace App\Jobs\Triggers;

use App\Enums\Triggers\SubscriptionStatus;
use App\Enums\Triggers\TriggerType;
use App\Exceptions\ConnectorException;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerSubscription;
use App\Services\Triggers\Push\PushSources;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Brings one trigger's push subscription in line with what the trigger now
 * wants: make it, correct it, or remove it.
 *
 * Publishing and switching a trigger on or off only queue this; they never
 * wait on a provider. Safe to run again at any time, and one at a time per
 * trigger.
 *
 * @see docs/APP_TRIGGERS_PLAN.md "Desired-state reconciler"
 */
class SyncTriggerSubscription implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Seconds to wait before each retry.
     *
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public function __construct(public string $triggerId)
    {
        $this->onQueue((string) config('triggers.fire_queue'));
    }

    /**
     * One reconcile per trigger at a time.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->triggerId))->releaseAfter(30)->expireAfter(120)];
    }

    /**
     * Make the subscription match the trigger.
     */
    public function handle(PushSources $sources): void
    {
        $trigger = Trigger::query()->find($this->triggerId);

        if ($trigger === null) {
            return;
        }

        $source = $trigger->type === TriggerType::Polling && $trigger->is_active
            ? $sources->forTrigger($trigger)
            : null;

        if ($source === null) {
            TriggerSubscription::query()->where('trigger_id', $trigger->id)->delete();

            return;
        }

        try {
            $routingKey = $source->routingKeyFor($trigger);
        } catch (ConnectorException $exception) {
            $this->record($trigger, $source->provider(), SubscriptionStatus::ReconnectNeeded, $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            // Out of reach for now (a network blip): the trigger keeps polling.
            $this->record($trigger, $source->provider(), SubscriptionStatus::Pending, $exception->getMessage());

            throw $exception;
        }

        if ($routingKey === null) {
            $this->record($trigger, $source->provider(), SubscriptionStatus::Failed, 'The connected account is not available.');

            return;
        }

        TriggerSubscription::query()->updateOrCreate(
            ['trigger_id' => $trigger->id],
            [
                'workspace_id' => $trigger->workspace_id,
                'provider' => $source->provider(),
                'mechanism' => 'push',
                'routing_key' => $routingKey,
                'status' => SubscriptionStatus::Active,
                'last_ok_at' => now(),
                'last_error' => null,
            ],
        );
    }

    /**
     * Keep the subscription row, with the reason it isn't receiving events.
     */
    private function record(Trigger $trigger, string $provider, SubscriptionStatus $status, string $error): void
    {
        TriggerSubscription::query()->updateOrCreate(
            ['trigger_id' => $trigger->id],
            [
                'workspace_id' => $trigger->workspace_id,
                'provider' => $provider,
                'mechanism' => 'push',
                'routing_key' => TriggerSubscription::query()->where('trigger_id', $trigger->id)->value('routing_key') ?? '',
                'status' => $status,
                'last_error' => mb_substr($error, 0, 500),
            ],
        );
    }
}
