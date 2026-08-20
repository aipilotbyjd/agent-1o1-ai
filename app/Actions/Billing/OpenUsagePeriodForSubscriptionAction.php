<?php

namespace App\Actions\Billing;

use App\Models\Billing\Plan;
use App\Models\Billing\UsagePeriod;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Attaches a plan/subscription to the workspace's current usage period (or
 * opens one, if none exists yet) — called when a subscription is created,
 * so `UsagePeriod::credits_limit` reflects the plan the workspace just paid
 * for. Updates the existing period in place rather than replacing it, so
 * `credits_used` already recorded this month before subscribing survives.
 *
 * Resetting `credits_limit` here is safe for purchased credits: packs top up
 * the workspace's own `topup_credits` pool, not the period, so a redelivered
 * `customer.subscription.updated` can no longer wipe them.
 */
class OpenUsagePeriodForSubscriptionAction
{
    public function execute(Workspace $workspace, ?Plan $plan, ?string $stripeSubscriptionId): UsagePeriod
    {
        // An unrecognised Stripe price leaves `$plan` null; falling back to
        // the default plan keeps the period metered instead of handing the
        // workspace an unlimited `credits_limit`.
        $plan ??= Plan::default();

        return DB::transaction(function () use ($workspace, $plan, $stripeSubscriptionId): UsagePeriod {
            $subscription = $stripeSubscriptionId !== null
                ? $workspace->subscriptions()->where('stripe_id', $stripeSubscriptionId)->first()
                : null;

            $current = $workspace->usagePeriods()
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($current !== null) {
                $current->update([
                    'plan_id' => $plan?->id,
                    'subscription_id' => $subscription?->id,
                    'credits_limit' => $plan?->creditsMonthly(),
                ]);

                return $current;
            }

            $startsAt = now()->startOfMonth();

            return $workspace->usagePeriods()->create([
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->clone()->endOfMonth()->addSecond(),
                'plan_id' => $plan?->id,
                'subscription_id' => $subscription?->id,
                'credits_limit' => $plan?->creditsMonthly(),
            ]);
        });
    }
}
