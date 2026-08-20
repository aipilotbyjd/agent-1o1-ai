<?php

namespace App\Enums\Billing;

enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';
    case Lifetime = 'lifetime';

    /**
     * Whether Stripe bills this interval on a repeating cycle. `Lifetime` is
     * a single payment, so it is bought through a `mode=payment` Checkout
     * Session and entitles the workspace via a `PlanGrant` rather than a
     * `Subscription` row — see `CheckoutLifetimePlanAction`.
     */
    public function isRecurring(): bool
    {
        return $this !== self::Lifetime;
    }

    /**
     * Whether this window is on sale estate-wide, per
     * `config('billing.intervals')`. Unknown keys default to enabled so a
     * newly added interval isn't silently unsellable until config catches up.
     */
    public function isEnabledGlobally(): bool
    {
        return (bool) config("billing.intervals.{$this->value}", true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::Yearly => 'Yearly',
            self::Lifetime => 'Lifetime',
        };
    }
}
