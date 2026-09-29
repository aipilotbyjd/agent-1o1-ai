<?php

namespace App\Http\Controllers\Api\Internal\V1\Admin\Referrals;

use App\Actions\Referrals\GrantManualReferralRewardAction;
use App\Actions\Referrals\GrantReferralRewardAction;
use App\Actions\Referrals\RevokeReferralRewardAction;
use App\Enums\Referrals\ReferralRewardStatus;
use App\Enums\Referrals\ReferralRewardType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Admin\Referrals\ReasonRequest;
use App\Http\Requests\Api\Internal\V1\Admin\Referrals\StoreManualReferralRewardRequest;
use App\Http\Resources\Api\Internal\V1\Admin\AdminReferralRewardResource;
use App\Http\Responses\ApiResponse;
use App\Models\Referrals\ReferralReward;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The reward ledger: review what is awaiting approval or on hold, approve,
 * grant early, revoke, or hand out a goodwill reward.
 */
class AdminReferralRewardController extends Controller
{
    public function __construct(
        private readonly GrantReferralRewardAction $grant,
        private readonly RevokeReferralRewardAction $revoke,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(ReferralRewardStatus::class)],
            'reward_type' => ['nullable', Rule::enum(ReferralRewardType::class)],
        ]);

        $rewards = ReferralReward::query()
            ->with(['recipient:id,name,email', 'plan'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('reward_type'), fn ($query) => $query->where('reward_type', $request->string('reward_type')))
            ->when($request->filled('recipient_user_id'), fn ($query) => $query->where('recipient_user_id', $request->string('recipient_user_id')))
            ->when($request->filled('referral_id'), fn ($query) => $query->where('referral_id', $request->string('referral_id')))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated(AdminReferralRewardResource::collection($rewards));
    }

    /**
     * Approves a reward held for manual review. It then follows its normal
     * hold: granted now if the hold has passed, else by
     * `referrals:grant-pending` when it does.
     */
    public function approve(Request $request, ReferralReward $reward)
    {
        abort_unless($reward->status === ReferralRewardStatus::AwaitingApproval, 422, 'Only a reward awaiting approval can be approved.');

        $reward->update(['status' => ReferralRewardStatus::Pending]);

        if ($reward->grant_after === null || $reward->grant_after->isPast()) {
            $this->grant->execute($reward, $request->user());
        }

        $this->audit->record($request->user(), 'referral_reward.approved', $reward, ['status' => ReferralRewardStatus::AwaitingApproval->value], ['status' => $reward->status->value]);

        return ApiResponse::success(['reward' => AdminReferralRewardResource::make($reward->refresh()->load('recipient', 'plan'))], 'Reward approved.');
    }

    public function grantNow(Request $request, ReferralReward $reward)
    {
        abort_unless($reward->status->isOpen(), 422, 'Only a pending or awaiting reward can be granted.');

        $previous = $reward->status;

        $this->grant->execute($reward, $request->user());

        $this->audit->record($request->user(), 'referral_reward.granted_early', $reward, ['status' => $previous->value], ['status' => ReferralRewardStatus::Granted->value]);

        return ApiResponse::success(['reward' => AdminReferralRewardResource::make($reward->refresh()->load('recipient', 'plan'))], 'Reward granted.');
    }

    public function revoke(ReasonRequest $request, ReferralReward $reward)
    {
        abort_if($reward->status === ReferralRewardStatus::Revoked, 422, 'This reward is already revoked.');

        $previous = $reward->status;

        $this->revoke->execute($reward, $request->validated('reason'));

        $this->audit->record($request->user(), 'referral_reward.revoked', $reward, ['status' => $previous->value], ['status' => ReferralRewardStatus::Revoked->value, 'reason' => $request->validated('reason')]);

        return ApiResponse::success(['reward' => AdminReferralRewardResource::make($reward->refresh()->load('recipient', 'plan'))], 'Reward revoked.');
    }

    public function store(StoreManualReferralRewardRequest $request, GrantManualReferralRewardAction $grantManual)
    {
        $data = $request->validated();

        $reward = $grantManual->execute(
            User::query()->findOrFail($data['user_id']),
            isset($data['workspace_id']) ? Workspace::query()->findOrFail($data['workspace_id']) : null,
            [...$data, 'reward_type' => ReferralRewardType::from($data['reward_type'])],
            $request->user(),
            $data['notes'] ?? null,
        );

        $this->audit->record($request->user(), 'referral_reward.manual_granted', $reward, null, $reward->attributesToArray());

        return ApiResponse::created(['reward' => AdminReferralRewardResource::make($reward->load('recipient', 'plan'))], 'Reward granted.');
    }
}
