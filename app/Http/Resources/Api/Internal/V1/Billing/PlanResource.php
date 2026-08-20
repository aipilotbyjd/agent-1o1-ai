<?php

namespace App\Http\Resources\Api\Internal\V1\Billing;

use App\Enums\Billing\BillingInterval;
use App\Models\Billing\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price_monthly' => $this->price_monthly,
            'price_quarterly' => $this->price_quarterly,
            'price_yearly' => $this->price_yearly,
            'price_lifetime' => $this->price_lifetime,
            'credits_monthly' => $this->credits_monthly,
            'limits' => $this->limits,
            'features' => $this->features,
            'trial_days' => $this->trial_days,
            // Only the intervals with a Stripe price configured, so the
            // billing screen can't offer a checkout that would 422 on submit.
            'available_intervals' => array_map(
                fn (BillingInterval $interval): string => $interval->value,
                $this->availableIntervals(),
            ),
        ];
    }
}
