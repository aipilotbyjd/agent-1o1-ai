<?php

namespace App\Http\Controllers\SuperAdmin\Referrals;

use App\Enums\Referrals\ReferralRewardStatus;
use App\Enums\Referrals\ReferralRewardType;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\ConfirmSupportActionRequest;
use App\Http\Requests\SuperAdmin\Referrals\ManualRewardRequest;
use App\Http\Requests\SuperAdmin\Referrals\ReasonRequest;
use App\Models\Billing\Plan;
use App\Models\Referrals\ReferralReward;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Referrals\ReferralAdmin;
use App\Services\Referrals\ReferralRuleDescriber;
use App\Services\SuperAdmin\SuperAdminReferrals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Referral rewards. Every action here moves credits, plan time or money,
 * so each one needs the super admin's password.
 */
class RewardController extends Controller
{
    private const int PER_PAGE = 50;

    public function __construct(
        private readonly ReferralAdmin $admin,
        private readonly SuperAdminReferrals $referrals,
    ) {}

    public function index(Request $request, ReferralRuleDescriber $describer): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ReferralRewardStatus::class)],
            'reward_type' => ['nullable', Rule::enum(ReferralRewardType::class)],
            'recipient' => ['nullable', 'uuid'],
        ]);

        $rewards = ReferralReward::query()
            ->with(['recipient:id,name,email', 'plan:id,name', 'workspace:id,name'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['reward_type'] ?? null, fn ($query, string $type) => $query->where('reward_type', $type))
            ->when($filters['recipient'] ?? null, fn ($query, string $recipient) => $query->where('recipient_user_id', $recipient))
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('super-admin.referrals.rewards.index', [
            'rewards' => $rewards,
            'summaries' => $rewards->getCollection()->mapWithKeys(fn (ReferralReward $reward): array => [$reward->id => $describer->rewardSummary($reward)]),
            'filters' => $filters,
        ]);
    }

    public function approve(ConfirmSupportActionRequest $request, ReferralReward $reward): RedirectResponse
    {
        $this->referrals->run($request->user('super_admin'), 'referral_reward.approved', $reward, fn (): ReferralReward => $this->admin->approveReward($reward, null));

        return back()->with('status', 'Reward approved.');
    }

    public function grantNow(ConfirmSupportActionRequest $request, ReferralReward $reward): RedirectResponse
    {
        $this->referrals->run($request->user('super_admin'), 'referral_reward.granted_now', $reward, fn (): ReferralReward => $this->admin->grantRewardNow($reward, null));

        return back()->with('status', 'Reward granted.');
    }

    public function revoke(ReasonRequest $request, ReferralReward $reward): RedirectResponse
    {
        $reason = $request->string('reason')->toString();

        $this->referrals->run($request->user('super_admin'), 'referral_reward.revoked', $reward, fn (): ReferralReward => $this->admin->revokeReward($reward, $reason, null), ['reason' => $reason]);

        return back()->with('status', 'Reward revoked and clawed back where possible.');
    }

    public function create(Request $request): View
    {
        $recipient = $request->filled('user')
            ? User::query()->with('workspaces:id,name')->find($request->validate(['user' => ['uuid']])['user'])
            : null;

        return view('super-admin.referrals.rewards.create', [
            'recipient' => $recipient,
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    /**
     * A goodwill reward outside any referral, e.g. as an apology.
     */
    public function store(ManualRewardRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('current_password');

        $reward = $this->referrals->run(
            $request->user('super_admin'),
            'referral_reward.manual_granted',
            null,
            fn (): ReferralReward => $this->admin->grantManualReward(
                User::query()->findOrFail($data['user_id']),
                isset($data['workspace_id']) ? Workspace::query()->findOrFail($data['workspace_id']) : null,
                [...$data, 'reward_type' => ReferralRewardType::from($data['reward_type'])],
                $data['notes'] ?? null,
                null,
            ),
            ['recipient_user_id' => $data['user_id'], 'reward_type' => $data['reward_type']],
        );

        return redirect()->route('super-admin.referrals.rewards.index', ['recipient' => $reward->recipient_user_id])->with('status', 'Goodwill reward granted.');
    }
}
