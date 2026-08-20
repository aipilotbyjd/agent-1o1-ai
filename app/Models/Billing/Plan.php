<?php

namespace App\Models\Billing;

use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\Feature;
use Database\Factories\Billing\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'price_monthly', 'price_quarterly', 'price_yearly', 'price_lifetime', 'credits_monthly', 'limits', 'features', 'stripe_product_id', 'stripe_price_id_monthly', 'stripe_price_id_quarterly', 'stripe_price_id_yearly', 'stripe_price_id_lifetime', 'enabled_intervals', 'trial_days', 'is_active', 'sort_order'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'features' => 'array',
            'enabled_intervals' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * The plan an unsubscribed (or lapsed) workspace falls back to, per
     * `config('billing.default_plan')`. Returns `null` when that slug isn't
     * seeded or is inactive — callers treat that as "no plan", i.e. an
     * unlimited allowance, so a half-seeded install fails open.
     */
    public static function default(): ?self
    {
        $slug = config('billing.default_plan');

        if ($slug === null) {
            return null;
        }

        return self::query()->where('slug', $slug)->where('is_active', true)->first();
    }

    public function creditsMonthly(): int
    {
        return $this->credits_monthly;
    }

    public function hasFeature(Feature $feature): bool
    {
        return (bool) ($this->features[$feature->value] ?? false);
    }

    public function stripePriceId(BillingInterval $interval): ?string
    {
        return match ($interval) {
            BillingInterval::Monthly => $this->stripe_price_id_monthly,
            BillingInterval::Quarterly => $this->stripe_price_id_quarterly,
            BillingInterval::Yearly => $this->stripe_price_id_yearly,
            BillingInterval::Lifetime => $this->stripe_price_id_lifetime,
        };
    }

    public function priceFor(BillingInterval $interval): int
    {
        return match ($interval) {
            BillingInterval::Monthly => $this->price_monthly,
            BillingInterval::Quarterly => $this->price_quarterly,
            BillingInterval::Yearly => $this->price_yearly,
            BillingInterval::Lifetime => $this->price_lifetime,
        };
    }

    /**
     * The intervals this plan can actually be bought on right now. Lets the
     * billing screen render exactly the options that will succeed, instead of
     * offering one that 422s on submit.
     *
     * @return list<BillingInterval>
     */
    public function availableIntervals(): array
    {
        return array_values(array_filter(
            BillingInterval::cases(),
            fn (BillingInterval $interval): bool => $this->isSoldOn($interval),
        ));
    }

    /**
     * Four independent things must hold for a purchase to be allowed, and any
     * one of them is a deliberate off switch:
     *
     * 1. the plan itself is active,
     * 2. the interval isn't withdrawn estate-wide (`config('billing.intervals')`),
     * 3. this plan enables the interval (`enabled_intervals`),
     * 4. a Stripe price is configured for it.
     *
     * This governs *selling* only. Entitlement is read from the subscription
     * or plan grant a workspace already holds, so withdrawing an interval —
     * or deactivating the plan outright — never revokes what someone already
     * paid for; it only stops new purchases.
     */
    public function isSoldOn(BillingInterval $interval): bool
    {
        return $this->is_active
            && $interval->isEnabledGlobally()
            && $this->enablesInterval($interval)
            && $this->stripePriceId($interval) !== null;
    }

    /**
     * A null `enabled_intervals` means "all of them" — rows predating the
     * column keep their original behaviour rather than becoming unsellable.
     */
    public function enablesInterval(BillingInterval $interval): bool
    {
        if ($this->enabled_intervals === null) {
            return true;
        }

        return in_array($interval->value, $this->enabled_intervals, strict: true);
    }

    /**
     * Why `$interval` can't be bought, phrased for the API's 422. Null when it
     * can be. Distinguishes "withdrawn" from "never configured" so an operator
     * reading the error knows whether to flip a switch or add a Stripe price.
     */
    public function unavailableReasonFor(BillingInterval $interval): ?string
    {
        return match (true) {
            ! $this->is_active => "Plan [{$this->slug}] is not available.",
            ! $interval->isEnabledGlobally() => "The [{$interval->value}] billing interval is not currently offered.",
            ! $this->enablesInterval($interval) => "Plan [{$this->slug}] is not sold on the [{$interval->value}] interval.",
            $this->stripePriceId($interval) === null => "Plan [{$this->slug}] has no Stripe price configured for [{$interval->value}].",
            default => null,
        };
    }
}
