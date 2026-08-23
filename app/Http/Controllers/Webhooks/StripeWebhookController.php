<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Billing\ActivateCreditPackAction;
use App\Actions\Billing\ActivatePlanGrantAction;
use App\Actions\Billing\OpenUsagePeriodForSubscriptionAction;
use App\Actions\Billing\RevokePlanGrantAction;
use App\Models\Billing\CreditPack;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Billing\ProcessedWebhookEvent;
use App\Notifications\Billing\PaymentFailedNotification;
use App\Notifications\Billing\PaymentRecoveredNotification;
use App\Notifications\Billing\SubscriptionCanceledNotification;
use App\Notifications\Billing\SubscriptionRenewedNotification;
use App\Services\Notifications\NotificationDispatcher;
use Carbon\Carbon;
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
     * Fulfils our two one-off (`mode=payment`) Checkout flows: credit-pack
     * top-ups and lifetime plan purchases. Subscription checkouts land here
     * too — Cashier's own sync handles those via
     * `customer.subscription.created` — so this acts only on sessions
     * carrying the matching metadata `type` we set at checkout.
     */
    protected function handleCheckoutSessionCompleted(array $payload): Response
    {
        $session = $payload['data']['object'] ?? [];
        $metadata = $session['metadata'] ?? [];
        $paymentIntentId = $session['payment_intent'] ?? null;

        match ($metadata['type'] ?? null) {
            'credit_pack' => $this->fulfilCreditPack($metadata, $paymentIntentId),
            'plan_grant' => $this->fulfilPlanGrant($metadata, $paymentIntentId),
            default => null,
        };

        return new Response('Webhook Handled');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function fulfilCreditPack(array $metadata, ?string $paymentIntentId): void
    {
        if (! isset($metadata['credit_pack_id'])) {
            return;
        }

        $pack = CreditPack::find($metadata['credit_pack_id']);

        if ($pack === null) {
            return;
        }

        $pack->update(['stripe_payment_intent_id' => $paymentIntentId]);

        app(ActivateCreditPackAction::class)->execute($pack);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function fulfilPlanGrant(array $metadata, ?string $paymentIntentId): void
    {
        if (! isset($metadata['plan_grant_id'])) {
            return;
        }

        $grant = PlanGrant::find($metadata['plan_grant_id']);

        if ($grant === null) {
            return;
        }

        $grant->update(['stripe_payment_intent_id' => $paymentIntentId]);

        app(ActivatePlanGrantAction::class)->execute($grant);
    }

    /**
     * A refunded lifetime purchase has to stop entitling the workspace. The
     * grant has no expiry and no subscription status to lapse, so nothing
     * else would ever withdraw it.
     */
    protected function handleChargeRefunded(array $payload): Response
    {
        $this->revokeGrantForPaymentIntent($payload['data']['object']['payment_intent'] ?? null);

        return new Response('Webhook Handled');
    }

    /**
     * Stripe pulls the funds as soon as a dispute is opened, so the grant is
     * withdrawn now rather than on resolution.
     */
    protected function handleChargeDisputeCreated(array $payload): Response
    {
        $this->revokeGrantForPaymentIntent($payload['data']['object']['payment_intent'] ?? null);

        return new Response('Webhook Handled');
    }

    private function revokeGrantForPaymentIntent(?string $paymentIntentId): void
    {
        if ($paymentIntentId === null) {
            return;
        }

        $grant = PlanGrant::query()->where('stripe_payment_intent_id', $paymentIntentId)->first();

        if ($grant === null) {
            return;
        }

        app(RevokePlanGrantAction::class)->execute($grant);
    }

    /**
     * Stripe fires this on every failed invoice charge attempt, including each
     * automatic retry. The subscription's own status transition (to
     * `past_due`) is synced by Cashier's handler; this records the dunning
     * cycle and tells the workspace what happened.
     *
     * No grace period is granted here or anywhere else: `past_due` stops
     * entitling the moment Stripe reports it, because every workflow run and
     * agent turn costs real model spend. The state recorded below exists to
     * *explain* that in-product, not to soften it.
     */
    protected function handleInvoicePaymentFailed(array $payload): Response
    {
        $invoice = $payload['data']['object'] ?? [];
        $customerId = $invoice['customer'] ?? null;

        if ($customerId === null) {
            return new Response('Webhook Handled');
        }

        $workspace = Cashier::findBillable($customerId);

        if ($workspace === null) {
            return new Response('Webhook Handled');
        }

        $attempts = max((int) ($invoice['attempt_count'] ?? 1), 1);
        $nextAttemptAt = isset($invoice['next_payment_attempt'])
            ? Carbon::createFromTimestampUTC($invoice['next_payment_attempt'])
            : null;

        // Resolved through the workspace rather than the invoice's own
        // subscription field, whose shape has moved between Stripe API
        // versions. Null for a one-off charge (a credit pack), which has no
        // dunning cycle to record but still warrants telling someone.
        $workspace->subscription('default')?->markDunning(
            $invoice['id'] ?? null,
            $attempts,
        );

        $dispatcher = app(NotificationDispatcher::class);

        $dispatcher->dispatch(
            $dispatcher->ownersAndAdmins($workspace),
            new PaymentFailedNotification($workspace, $attempts, $invoice['id'] ?? null, $nextAttemptAt),
        );

        return new Response('Webhook Handled');
    }

    /**
     * Collection succeeded. Closes any open dunning cycle and says so — but
     * only if there was one, so an ordinary monthly renewal doesn't generate a
     * "payment recovered" notification nobody was waiting for. An ordinary
     * renewal (Stripe's `billing_reason: subscription_cycle`, with no
     * dunning cycle to close) gets its own quieter notification instead — the
     * first charge on checkout (`subscription_create`) stays silent, since
     * the checkout response already confirmed it.
     */
    protected function handleInvoicePaymentSucceeded(array $payload)
    {
        $response = parent::handleInvoicePaymentSucceeded($payload);

        $invoice = $payload['data']['object'] ?? [];
        $customerId = $invoice['customer'] ?? null;

        if ($customerId === null) {
            return $response;
        }

        $workspace = Cashier::findBillable($customerId);
        $recovered = $workspace?->subscription('default')?->clearDunning() ?? false;

        if ($workspace === null) {
            return $response;
        }

        $dispatcher = app(NotificationDispatcher::class);

        if ($recovered) {
            $dispatcher->dispatch(
                $dispatcher->ownersAndAdmins($workspace),
                new PaymentRecoveredNotification($workspace),
            );
        } elseif (($invoice['billing_reason'] ?? null) === 'subscription_cycle') {
            $dispatcher->dispatch(
                $dispatcher->ownersAndAdmins($workspace),
                new SubscriptionRenewedNotification($workspace, $invoice['id'] ?? null),
            );
        }

        return $response;
    }

    /**
     * The end of the line. Stripe deletes a subscription both when a customer
     * cancels and when it gives up after exhausting its retry schedule — an
     * open dunning cycle is what separates the two, so it's read before
     * Cashier's handler clears the row's status.
     */
    protected function handleCustomerSubscriptionDeleted(array $payload)
    {
        $customerId = $payload['data']['object']['customer'] ?? null;
        $workspace = $customerId !== null ? Cashier::findBillable($customerId) : null;
        $subscription = $workspace?->subscription('default');
        $afterFailedPayments = $subscription?->inDunning() ?? false;

        $response = parent::handleCustomerSubscriptionDeleted($payload);

        if ($workspace !== null) {
            $subscription?->clearDunning();

            $dispatcher = app(NotificationDispatcher::class);

            $dispatcher->dispatch(
                $dispatcher->ownersAndAdmins($workspace),
                new SubscriptionCanceledNotification($workspace, $afterFailedPayments),
            );
        }

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
