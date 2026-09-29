<?php

namespace App\Http\Resources\Api\Internal\V1\Billing;

use App\Models\Billing\CreditTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `credits` is the whole charge. `topup_credits` and `overage_credits` say
 * how it was funded — out of the purchased top-up pool, and out of billable
 * overage — so the credit-log screen can mark the rows that will turn into
 * an invoice line. The remainder came out of the plan allowance.
 *
 * @mixin CreditTransaction
 */
class CreditTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'credits' => $this->credits,
            'topup_credits' => $this->topup_credits,
            'overage_credits' => $this->overage_credits,
            'reason' => $this->reason,
            'created_at' => $this->created_at,
        ];
    }
}
