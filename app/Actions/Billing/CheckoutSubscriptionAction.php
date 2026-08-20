<?php

namespace App\Actions\Billing;

use App\Enums\Billing\BillingInterval;
use App\Models\Billing\Plan;
use App\Models\Workspaces\Workspace;
use Laravel\Cashier\Checkout;

class CheckoutSubscriptionAction
{
    private const SUBSCRIPTION_TYPE = 'default';

    /**
     * Returns a Stripe Checkout URL for a workspace with no subscription yet,
     * or swaps the existing subscription's price in place and returns null.
     *
     * Recurring intervals only. `Lifetime` is a one-off `mode=payment`
     * purchase with no subscription behind it, so it goes through
     * `CheckoutLifetimePlanAction` — guarded here rather than left to fail
     * deeper in Cashier with an unrecurring price.
     */
    public function execute(Workspace $workspace, Plan $plan, BillingInterval $interval): ?string
    {
        abort_if(
            ! $interval->isRecurring(),
            422,
            "Interval [{$interval->value}] is not a recurring subscription.",
        );

        $priceId = $plan->stripePriceId($interval);

        abort_if($priceId === null, 422, "Plan [{$plan->slug}] has no Stripe price configured for [{$interval->value}].");

        $subscription = $workspace->subscription(self::SUBSCRIPTION_TYPE);

        if ($subscription !== null) {
            $subscription->swap($priceId);
            $subscription->update(['plan_id' => $plan->id]);

            return null;
        }

        $builder = $workspace->newSubscription(self::SUBSCRIPTION_TYPE, $priceId);

        if ($plan->trial_days > 0) {
            $builder = $builder->trialDays($plan->trial_days);
        }

        /** @var Checkout $checkout */
        $checkout = $builder->checkout([
            'success_url' => $this->successUrl($workspace),
            'cancel_url' => $this->cancelUrl($workspace),
        ]);

        return $checkout->asStripeCheckoutSession()->url;
    }

    private function successUrl(Workspace $workspace): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/workspaces/{$workspace->slug}/billing?checkout=success";
    }

    private function cancelUrl(Workspace $workspace): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/workspaces/{$workspace->slug}/billing?checkout=cancel";
    }
}
