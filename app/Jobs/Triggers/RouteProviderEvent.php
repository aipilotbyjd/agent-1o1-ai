<?php

namespace App\Jobs\Triggers;

use App\Enums\Triggers\ProviderEventStatus;
use App\Enums\Triggers\SubscriptionStatus;
use App\Enums\Triggers\TriggerType;
use App\Models\Triggers\ProviderEvent;
use App\Models\Triggers\TriggerSubscription;
use App\Services\Triggers\Push\PushSources;
use App\Services\Triggers\TriggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Hands one stored provider event to every trigger subscribed to it.
 *
 * Each trigger gets the event through the usual intake (TriggerService::
 * receive), so dedupe, filters, credits, caps and the circuit breaker all
 * apply as they do for any other source. One trigger failing never keeps the
 * others from getting the event.
 */
class RouteProviderEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Seconds to wait before each retry.
     *
     * @var list<int>
     */
    public array $backoff = [5, 30];

    public function __construct(public ProviderEvent $event)
    {
        $this->onQueue((string) config('triggers.fire_queue'));
    }

    /**
     * Route the event to the triggers whose subscription matches it.
     */
    public function handle(PushSources $sources, TriggerService $triggers): void
    {
        $this->event->refresh();

        if ($this->event->status !== ProviderEventStatus::Received) {
            return;
        }

        $source = $sources->source($this->event->provider);

        if ($source === null || $this->event->routing_key === null) {
            $this->event->forceFill(['status' => ProviderEventStatus::Dropped, 'processed_at' => now()])->save();

            return;
        }

        $item = $source->itemFor($this->event->payload);
        $itemId = $source->itemId($item);
        $routed = 0;

        TriggerSubscription::query()
            ->where('provider', $this->event->provider)
            ->where('routing_key', $this->event->routing_key)
            ->whereIn('status', array_map(fn (SubscriptionStatus $status): string => $status->value, array_filter(SubscriptionStatus::cases(), fn (SubscriptionStatus $status): bool => $status->receivesEvents())))
            ->with('trigger.workspace')
            ->eachById(function (TriggerSubscription $subscription) use ($source, $triggers, $item, $itemId, &$routed): void {
                $trigger = $subscription->trigger;

                if ($trigger === null || ! $trigger->is_active || $itemId === '' || ! $source->accepts($trigger, $item)) {
                    return;
                }

                try {
                    $triggers->receive($trigger, TriggerType::Webhook, $item, $itemId);
                    $subscription->forceFill(['last_event_at' => now(), 'last_ok_at' => now()])->save();
                    $routed++;
                } catch (Throwable $exception) {
                    report($exception);
                }
            });

        $this->event->forceFill([
            'status' => $routed > 0 ? ProviderEventStatus::Routed : ProviderEventStatus::Dropped,
            'processed_at' => now(),
        ])->save();
    }

    /**
     * Mark the event failed once every retry is spent.
     */
    public function failed(?Throwable $exception): void
    {
        $this->event->forceFill([
            'status' => ProviderEventStatus::Failed,
            'error' => $exception !== null ? mb_substr($exception->getMessage(), 0, 500) : null,
            'processed_at' => now(),
        ])->save();
    }
}
