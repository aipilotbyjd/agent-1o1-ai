<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Billing\OpenUsagePeriodForSubscriptionAction;
use App\Models\Billing\Plan;
use App\Models\Billing\ProcessedWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends CashierWebhookController
{
    /**
     * Guards every event with an idempotency check before Cashier (or our own
     * handlers) process it — Stripe retries webhook delivery, and without
     * this a redelivered event could sync plan/usage-period state twice.
     */
    public function handleWebhook(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        $eventId = $payload['id'] ?? null;

        if ($eventId === null) {
            return $this->missingMethod($payload ?? []);
        }

        $isNewEvent = DB::transaction(function () use ($eventId, $payload) {
            if (ProcessedWebhookEvent::query()->where('stripe_event_id', $eventId)->lockForUpdate()->exists()) {
                return false;
            }

            ProcessedWebhookEvent::create([
                'stripe_event_id' => $eventId,
                'type' => $payload['type'] ?? 'unknown',
                'processed_at' => now(),
            ]);

            return true;
        });

        if (! $isNewEvent) {
            return new Response('Webhook already processed', 200);
        }

        return parent::handleWebhook($request);
    }

    protected function handleCustomerSubscriptionCreated(array $payload)
    {
        $response = parent::handleCustomerSubscriptionCreated($payload);

        $this->syncPlanAndUsagePeriod($payload);

        return $response;
    }

    protected function handleCustomerSubscriptionUpdated(array $payload)
    {
        $response = parent::handleCustomerSubscriptionUpdated($payload);

        $this->syncPlanAndUsagePeriod($payload);

        return $response;
    }

    private function syncPlanAndUsagePeriod(array $payload): void
    {
        $customerId = $payload['data']['object']['customer'] ?? null;
        $stripeSubscriptionId = $payload['data']['object']['id'] ?? null;
        $stripePriceId = $payload['data']['object']['items']['data'][0]['price']['id'] ?? null;

        if ($customerId === null || $stripeSubscriptionId === null) {
            return;
        }

        $workspace = Cashier::findBillable($customerId);

        if ($workspace === null) {
            return;
        }

        $plan = $stripePriceId !== null
            ? Plan::query()
                ->where('stripe_price_id_monthly', $stripePriceId)
                ->orWhere('stripe_price_id_yearly', $stripePriceId)
                ->first()
            : null;

        if ($plan !== null) {
            $workspace->subscriptions()
                ->where('stripe_id', $stripeSubscriptionId)
                ->update(['plan_id' => $plan->id]);
        }

        app(OpenUsagePeriodForSubscriptionAction::class)->execute($workspace, $plan, $stripeSubscriptionId);
    }
}
