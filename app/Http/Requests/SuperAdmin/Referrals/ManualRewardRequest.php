<?php

namespace App\Http\Requests\SuperAdmin\Referrals;

use App\Http\Requests\Api\Internal\V1\Admin\Referrals\StoreManualReferralRewardRequest;

/**
 * The API's goodwill-reward rules plus the super admin's password: it hands
 * out credits, plan time or Stripe balance directly.
 */
class ManualRewardRequest extends StoreManualReferralRewardRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'current_password' => ['required', 'string', 'current_password:super_admin'],
        ];
    }
}
