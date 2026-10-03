<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Billing\ActivateCreditPackAction;
use App\Actions\Billing\ActivatePlanGrantAction;
use App\Actions\Billing\OpenUsagePeriodForSubscriptionAction;
use App\Actions\Billing\RevokePlanGrantAction;
use App\Enums\Billing\BillingInterval;
use App\Enums\Referrals\ReferralPaymentSource;
use App\Jobs\Referrals\ProcessReferralPaymentJob;
use App\Jobs\Referrals\ProcessReferralRefundJob;
use App\Models\Billing\CreditPack;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Billing\ProcessedWebhookEvent;
use App\Models\Workspaces\Workspace;
use App\Notifications\Billing\PaymentFailedNotification;
use App\Notifications\Billing\PaymentRecoveredNotification;
use App\Notifications\Billing\SubscriptionCanceledNotification;
use App\Notifications\Billing\SubscriptionRenewedNotification;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Referrals\ReferralPaymentData;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class StripeWebhookController extends CashierWebhookController
{
    public function __construct()
    {
        parent::__construct();

        // Cashier only verifies the signature when a secret is configured, so
        // a missing one would let anyone forge events that grant credits or
        // plans. Refuse to serve the endpoint at all rather than fail open.
        $this->middleware(function (Request $request, Closure $next) {
            abort_if(
                blank(config('cashier.webhook.secret')) && ! app()->environment(['local', 'testing']),
                503,
                'The Stripe webhook signing secret is not configured.',
            );

            return $next($request);
        });
    }

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

        $this->recordReferralCheckoutPayment($session, $metadata, $paymentIntentId);

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
        $charge = $payload['data']['object'] ?? [];

        $this->revokeGrantForPaymentIntent($charge['payment_intent'] ?? null);

        $fullyRefunded = ($charge['refunded'] ?? false) === true
            || (isset($charge['amount'], $charge['amount_refunded']) && $charge['amount_refunded'] >= $charge['amount']);

        $this->withdrawReferralRewards($charge['payment_intent'] ?? null, $fullyRefunded, 'Payment refunded');

        return new Response('Webhook Handled');
    }

    /**
     * Stripe pulls the funds as soon as a dispute is opened, so the grant is
     * withdrawn now rather than on resolution.
     */
    protected function handleChargeDisputeCreated(array $payload): Response
    {
        $paymentIntentId = $payload['data']['object']['payment_intent'] ?? null;

        $this->revokeGrantForPaymentIntent($paymentIntentId);
        $this->withdrawReferralRewards($paymentIntentId, true, 'Payment disputed');

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

        $this->recordReferralInvoicePayment($workspace, $invoice);

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
            ? Plan::findByStripePriceId($stripePriceId)
            : null;

        if ($plan !== null) {
            $workspace->subscriptions()
                ->where('stripe_id', $stripeSubscriptionId)
                ->update(['plan_id' => $plan->id]);
        }

        app(OpenUsagePeriodForSubscriptionAction::class)->execute($workspace, $plan, $stripeSubscriptionId);
    }

    /**
     * Hands a paid subscription invoice to the referral program. Tax is
     * taken out (Stripe's `total` minus `total_excluding_tax`) and a $0
     * invoice — a trial start — never counts as a payment. Failures are
     * reported, never allowed to fail the webhook.
     *
     * @param  array<string, mixed>  $invoice
     */
    private function recordReferralInvoicePayment(Workspace $workspace, array $invoice): void
    {
        $this->safelyForReferrals(function () use ($workspace, $invoice): void {
            $tax = max(0, (int) ($invoice['total'] ?? 0) - (int) ($invoice['total_excluding_tax'] ?? $invoice['total'] ?? 0));
            $amount = max(0, (int) ($invoice['amount_paid'] ?? 0) - $tax);

            if ($amount <= 0 || ! isset($invoice['id'])) {
                return;
            }

            $line = $invoice['lines']['data'][0] ?? [];
            $priceId = $line['price']['id'] ?? $line['pricing']['price_details']['price'] ?? null;
            $plan = $priceId !== null ? Plan::findByStripePriceId($priceId) : null;
            $interval = $plan !== null
                ? collect(BillingInterval::cases())->first(fn (BillingInterval $interval): bool => $plan->stripePriceId($interval) === $priceId)
                : null;

            ProcessReferralPaymentJob::dispatch(new ReferralPaymentData(
                workspaceId: $workspace->id,
                reference: $invoice['id'],
                // Newer Stripe API versions moved the intent under `payments`.
                paymentIntentId: $invoice['payment_intent'] ?? $invoice['payments']['data'][0]['payment']['payment_intent'] ?? null,
                source: ReferralPaymentSource::Subscription,
                amountCents: $amount,
                currency: $invoice['currency'] ?? 'usd',
                planId: $plan?->id,
                interval: $interval,
            ));
        });
    }

    /**
     * Hands a one-off Checkout purchase (credit pack or lifetime plan) to
     * the referral program, keyed by its payment intent.
     *
     * @param  array<string, mixed>  $session
     * @param  array<string, mixed>  $metadata
     */
    private function recordReferralCheckoutPayment(array $session, array $metadata, ?string $paymentIntentId): void
    {
        $this->safelyForReferrals(function () use ($session, $metadata, $paymentIntentId): void {
            $purchase = match ($metadata['type'] ?? null) {
                'credit_pack' => isset($metadata['credit_pack_id']) ? CreditPack::find($metadata['credit_pack_id']) : null,
                'plan_grant' => isset($metadata['plan_grant_id']) ? PlanGrant::find($metadata['plan_grant_id']) : null,
                default => null,
            };

            $amount = max(0, (int) ($session['amount_total'] ?? 0) - (int) ($session['total_details']['amount_tax'] ?? 0));

            if ($purchase === null || $paymentIntentId === null || $amount <= 0) {
                return;
            }

            ProcessReferralPaymentJob::dispatch(new ReferralPaymentData(
                workspaceId: $purchase->workspace_id,
                reference: $paymentIntentId,
                paymentIntentId: $paymentIntentId,
                source: $purchase instanceof PlanGrant ? ReferralPaymentSource::Lifetime : ReferralPaymentSource::CreditPack,
                amountCents: $amount,
                currency: $session['currency'] ?? 'usd',
                planId: $purchase instanceof PlanGrant ? $purchase->plan_id : null,
                interval: $purchase instanceof PlanGrant ? BillingInterval::Lifetime : null,
            ));
        });
    }

    private function withdrawReferralRewards(?string $paymentIntentId, bool $fullyRefunded, string $reason): void
    {
        if ($paymentIntentId === null) {
            return;
        }

        $this->safelyForReferrals(fn () => ProcessReferralRefundJob::dispatch($paymentIntentId, $fullyRefunded, $reason));
    }

    /**
     * The referral program rides along on billing webhooks; a bug in it
     * must never turn a Stripe delivery into a 5xx and a retry storm.
     */
    private function safelyForReferrals(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
