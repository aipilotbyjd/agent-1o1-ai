<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Billing\ActivateCreditPackAction;
use App\Actions\Billing\OpenUsagePeriodForSubscriptionAction;
use App\Models\Billing\CreditPack;
use App\Models\Billing\Plan;
use App\Models\Billing\ProcessedWebhookEvent;
use App\Notifications\Billing\PaymentFailedNotification;
use App\Services\Notifications\NotificationDispatcher;
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

    /**
     * Activates a `CreditPack` once its one-off Checkout Session completes.
     * Subscription checkouts land here too (Cashier's own subscription sync
     * handles those via `customer.subscription.created`), so this only acts
     * when the session carries our `credit_pack` metadata.
     */
    protected function handleCheckoutSessionCompleted(array $payload): Response
    {
        $session = $payload['data']['object'] ?? [];
        $metadata = $session['metadata'] ?? [];

        if (($metadata['type'] ?? null) === 'credit_pack' && isset($metadata['credit_pack_id'])) {
            $pack = CreditPack::find($metadata['credit_pack_id']);

            if ($pack !== null) {
                $pack->update(['stripe_payment_intent_id' => $session['payment_intent'] ?? null]);

                app(ActivateCreditPackAction::class)->execute($pack);
            }
        }

        return new Response('Webhook Handled');
    }

    /**
     * Stripe fires this on every failed invoice charge attempt (including
     * retries) — the underlying subscription's status transitions (e.g. to
     * `past_due`) are synced by Cashier's own handler; this only notifies
     * the workspace owner.
     */
    protected function handleInvoicePaymentFailed(array $payload): Response
    {
        $customerId = $payload['data']['object']['customer'] ?? null;

        if ($customerId === null) {
            return new Response('Webhook Handled');
        }

        $workspace = Cashier::findBillable($customerId);

        if ($workspace !== null) {
            $dispatcher = app(NotificationDispatcher::class);

            $dispatcher->dispatch(
                $dispatcher->ownersAndAdmins($workspace),
                new PaymentFailedNotification($workspace),
            );
        }

        return new Response('Webhook Handled');
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
