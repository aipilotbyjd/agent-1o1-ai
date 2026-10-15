<?php

namespace App\Http\Controllers\SuperAdmin\Referrals;

use App\Enums\Referrals\ReferralStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\ConfirmSupportActionRequest;
use App\Http\Requests\SuperAdmin\Referrals\ReasonRequest;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralProgram;
use App\Services\Referrals\ReferralAdmin;
use App\Services\Referrals\ReferralRuleDescriber;
use App\Services\SuperAdmin\SuperAdminReferrals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReferralController extends Controller
{
    private const int PER_PAGE = 50;

    public function __construct(
        private readonly ReferralAdmin $admin,
        private readonly SuperAdminReferrals $referrals,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ReferralStatus::class)],
            'program' => ['nullable', 'uuid'],
            'referrer' => ['nullable', 'uuid'],
        ]);

        return view('super-admin.referrals.referrals.index', [
            'referrals' => Referral::query()
                ->with(['code:id,code', 'referrer:id,name,email', 'referredUser:id,name,email', 'program:id,name'])
                ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->when($filters['program'] ?? null, fn ($query, string $program) => $query->where('program_id', $program))
                ->when($filters['referrer'] ?? null, fn ($query, string $referrer) => $query->where('referrer_user_id', $referrer))
                ->latest()
                ->paginate(self::PER_PAGE)
                ->withQueryString(),
            'programs' => ReferralProgram::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function show(Referral $referral, ReferralRuleDescriber $describer): View
    {
        $referral->load(['code:id,code', 'referrer:id,name,email', 'referredUser:id,name,email', 'program:id,name', 'rewards.plan:id,name', 'rewards.recipient:id,email'])
            ->loadCount('payments');

        return view('super-admin.referrals.referrals.show', [
            'referral' => $referral,
            'rewardSummaries' => $referral->rewards->mapWithKeys(fn ($reward): array => [$reward->id => $describer->rewardSummary($reward)]),
        ]);
    }

    /**
     * Marks the referral fraudulent or ineligible and claws back every
     * reward it produced.
     */
    public function reject(ReasonRequest $request, Referral $referral): RedirectResponse
    {
        $reason = $request->string('reason')->toString();

        $this->referrals->run($request->user('super_admin'), 'referral.rejected', $referral, fn (): Referral => $this->admin->rejectReferral($referral, $reason, null), ['reason' => $reason]);

        return redirect()->route('super-admin.referrals.referrals.show', $referral)->with('status', 'Referral rejected and its rewards withdrawn.');
    }

    public function restore(ConfirmSupportActionRequest $request, Referral $referral): RedirectResponse
    {
        $this->referrals->run($request->user('super_admin'), 'referral.restored', $referral, fn (): Referral => $this->admin->restoreReferral($referral, null));

        return redirect()->route('super-admin.referrals.referrals.show', $referral)->with('status', 'Referral restored.');
    }
}
