<?php

namespace App\Http\Controllers\SuperAdmin\Referrals;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Referrals\RuleRequest;
use App\Models\Billing\Plan;
use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralRewardRule;
use App\Services\Referrals\ReferralAdmin;
use App\Services\SuperAdmin\SuperAdminReferrals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RuleController extends Controller
{
    public function __construct(
        private readonly ReferralAdmin $admin,
        private readonly SuperAdminReferrals $referrals,
    ) {}

    public function create(ReferralProgram $program): View
    {
        return view('super-admin.referrals.rules.form', [
            'program' => $program,
            'rule' => new ReferralRewardRule(['is_active' => true]),
            'plans' => Plan::query()->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(RuleRequest $request, ReferralProgram $program): RedirectResponse
    {
        $this->referrals->run(
            $request->user('super_admin'),
            'referral_rule.created',
            null,
            fn (): ReferralRewardRule => $this->admin->createRule($program, $request->validated(), null),
            ['program_id' => $program->id],
        );

        return redirect()->route('super-admin.referrals.programs.show', $program)->with('status', 'Rule added.');
    }

    public function edit(ReferralRewardRule $rule): View
    {
        return view('super-admin.referrals.rules.form', [
            'program' => $rule->program,
            'rule' => $rule,
            'plans' => Plan::query()->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function update(RuleRequest $request, ReferralRewardRule $rule): RedirectResponse
    {
        $this->referrals->run(
            $request->user('super_admin'),
            'referral_rule.updated',
            $rule,
            fn (): ReferralRewardRule => $this->admin->updateRule($rule, $request->validated(), null),
            ['fields' => array_keys($request->validated())],
        );

        return redirect()->route('super-admin.referrals.programs.show', $rule->program_id)->with('status', 'Rule saved.');
    }

    public function destroy(Request $request, ReferralRewardRule $rule): RedirectResponse
    {
        $programId = $rule->program_id;

        $this->referrals->run($request->user('super_admin'), 'referral_rule.deleted', $rule, fn () => $this->admin->deleteRule($rule, null));

        return redirect()->route('super-admin.referrals.programs.show', $programId)->with('status', 'Rule deleted.');
    }

    public function toggle(Request $request, ReferralRewardRule $rule): RedirectResponse
    {
        $rule = $this->referrals->run($request->user('super_admin'), 'referral_rule.toggled', $rule, fn (): ReferralRewardRule => $this->admin->toggleRule($rule, null));

        return back()->with('status', $rule->is_active ? 'Rule switched on.' : 'Rule switched off.');
    }

    /**
     * Swaps a rule with its neighbour; rules are evaluated in this order.
     */
    public function move(Request $request, ReferralRewardRule $rule): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        $program = $rule->program;

        $ids = $program->rules()->orderBy('sort_order')->orderBy('created_at')->pluck('id')->all();
        $index = array_search($rule->id, $ids, true);
        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || ! isset($ids[$swapWith])) {
            return back();
        }

        [$ids[$index], $ids[$swapWith]] = [$ids[$swapWith], $ids[$index]];

        $this->referrals->run($request->user('super_admin'), 'referral_rule.reordered', $program, fn () => $this->admin->reorderRules($program, $ids, null), ['rule_ids' => $ids]);

        return back()->with('status', 'Rule order saved.');
    }
}
