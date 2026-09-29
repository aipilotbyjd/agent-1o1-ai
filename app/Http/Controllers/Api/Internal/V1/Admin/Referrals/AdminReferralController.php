<?php

namespace App\Http\Controllers\Api\Internal\V1\Admin\Referrals;

use App\Enums\Referrals\ReferralStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Admin\Referrals\ReasonRequest;
use App\Http\Resources\Api\Internal\V1\Admin\AdminReferralResource;
use App\Http\Responses\ApiResponse;
use App\Models\Referrals\Referral;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Referrals\ReferralLifecycle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminReferralController extends Controller
{
    public function __construct(
        private readonly ReferralLifecycle $lifecycle,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', Rule::enum(ReferralStatus::class)]]);

        $referrals = Referral::query()
            ->with(['code:id,code', 'referrer:id,name,email', 'referredUser:id,name,email'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('program_id'), fn ($query) => $query->where('program_id', $request->string('program_id')))
            ->when($request->filled('referrer_user_id'), fn ($query) => $query->where('referrer_user_id', $request->string('referrer_user_id')))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated(AdminReferralResource::collection($referrals));
    }

    public function show(Referral $referral)
    {
        $referral->load(['code:id,code', 'referrer:id,name,email', 'referredUser:id,name,email', 'rewards.plan'])->loadCount('payments');

        return ApiResponse::success(['referral' => AdminReferralResource::make($referral)]);
    }

    /**
     * Marks a referral fraudulent (or otherwise ineligible) and withdraws
     * every reward it produced.
     */
    public function reject(ReasonRequest $request, Referral $referral)
    {
        abort_if($referral->isRejected(), 422, 'This referral is already rejected.');

        $previous = $referral->status;

        $this->lifecycle->reject($referral, $request->validated('reason'));

        $this->audit->record($request->user(), 'referral.rejected', $referral, ['status' => $previous->value], ['status' => ReferralStatus::Rejected->value, 'reason' => $request->validated('reason')]);

        return ApiResponse::success(['referral' => AdminReferralResource::make($referral->refresh()->load('rewards'))], 'Referral rejected.');
    }

    public function restore(Request $request, Referral $referral)
    {
        abort_unless($referral->isRejected(), 422, 'Only a rejected referral can be restored.');

        $reason = $referral->rejection_reason;

        $this->lifecycle->restore($referral);

        $this->audit->record($request->user(), 'referral.restored', $referral, ['status' => ReferralStatus::Rejected->value, 'reason' => $reason], ['status' => $referral->status->value]);

        return ApiResponse::success(['referral' => AdminReferralResource::make($referral->refresh())], 'Referral restored.');
    }
}
