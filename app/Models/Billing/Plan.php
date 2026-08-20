<?php

namespace App\Models\Billing;

use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\Feature;
use Database\Factories\Billing\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'price_monthly', 'price_yearly', 'credits_monthly', 'limits', 'features', 'stripe_product_id', 'stripe_price_id_monthly', 'stripe_price_id_yearly', 'trial_days', 'is_active', 'sort_order'])]
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
            BillingInterval::Yearly => $this->stripe_price_id_yearly,
        };
    }
}
