<?php

namespace App\Http\Resources\Api\Internal\V1\Billing;

use App\Models\Billing\CreditTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
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
            'reason' => $this->reason,
            'created_at' => $this->created_at,
        ];
    }
}
