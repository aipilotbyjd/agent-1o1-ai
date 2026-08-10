<?php

namespace App\Http\Resources\Api\Internal\V1\Billing;

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
            'price_yearly' => $this->price_yearly,
            'credits_monthly' => $this->credits_monthly,
            'limits' => $this->limits,
            'features' => $this->features,
            'trial_days' => $this->trial_days,
        ];
    }
}
