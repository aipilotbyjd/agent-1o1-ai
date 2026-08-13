<?php

namespace App\Http\Resources\Api\Internal\V1\Billing;

use App\Models\Billing\UsagePeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UsagePeriod
 */
class UsagePeriodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'credits_used' => $this->credits_used,
            'credits_limit' => $this->credits_limit,
            'credits_remaining' => $this->credits_limit === null ? null : max(0, $this->credits_limit - $this->credits_used),
        ];
    }
}
