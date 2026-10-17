<?php

namespace App\Http\Controllers\SuperAdmin\Referrals;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Referrals\ProgramRequest;
use App\Models\Billing\Plan;
use App\Models\Referrals\ReferralProgram;
use App\Services\Referrals\ReferralAdmin;
use App\Services\Referrals\ReferralRuleDescriber;
use App\Services\SuperAdmin\SuperAdminReferrals;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProgramController extends Controller
{
    public function __construct(
        private readonly ReferralAdmin $admin,
        private readonly SuperAdminReferrals $referrals,
    ) {}

    public function index(): View
    {
        return view('super-admin.referrals.programs.index', [
            'programs' => ReferralProgram::query()
                ->withCount(['rules', 'referrals'])
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('super-admin.referrals.programs.form', [
            'program' => new ReferralProgram,
            'plans' => $this->plans(),
        ]);
    }

    public function store(ProgramRequest $request): RedirectResponse
    {
        $program = $this->referrals->run(
            $request->user('super_admin'),
            'referral_program.created',
            null,
            fn (): ReferralProgram => $this->admin->createProgram($request->validated(), null),
        );

        return redirect()->route('super-admin.referrals.programs.show', $program)->with('status', 'Program created. Add reward rules to it below.');
    }

    public function show(ReferralProgram $program, ReferralRuleDescriber $describer): View
    {
        $program->load(['rules' => fn ($query) => $query->orderBy('sort_order'), 'rules.plan:id,name'])->loadCount('referrals');

        return view('super-admin.referrals.programs.show', [
            'program' => $program,
            'descriptions' => $program->rules->mapWithKeys(fn ($rule): array => [$rule->id => $describer->describe($rule)]),
        ]);
    }

    public function edit(ReferralProgram $program): View
    {
        return view('super-admin.referrals.programs.form', [
            'program' => $program,
            'plans' => $this->plans(),
        ]);
    }

    public function update(ProgramRequest $request, ReferralProgram $program): RedirectResponse
    {
        $this->referrals->run(
            $request->user('super_admin'),
            'referral_program.updated',
            $program,
            fn (): ReferralProgram => $this->admin->updateProgram($program, $request->validated(), null),
            ['fields' => array_keys($request->validated())],
        );

        return redirect()->route('super-admin.referrals.programs.show', $program)->with('status', 'Program saved.');
    }

    public function destroy(Request $request, ReferralProgram $program): RedirectResponse
    {
        $this->referrals->run($request->user('super_admin'), 'referral_program.deleted', $program, fn () => $this->admin->deleteProgram($program, null));

        return redirect()->route('super-admin.referrals.programs.index')->with('status', 'Program deleted.');
    }

    public function makeDefault(Request $request, ReferralProgram $program): RedirectResponse
    {
        $this->referrals->run($request->user('super_admin'), 'referral_program.made_default', $program, fn (): ReferralProgram => $this->admin->makeDefault($program, null));

        return back()->with('status', "{$program->name} is now the default program.");
    }

    public function duplicate(Request $request, ReferralProgram $program): RedirectResponse
    {
        $copy = $this->referrals->run(
            $request->user('super_admin'),
            'referral_program.duplicated',
            null,
            fn (): ReferralProgram => $this->admin->duplicateProgram($program, null),
            ['source_program_id' => $program->id],
        );

        return redirect()->route('super-admin.referrals.programs.show', $copy)->with('status', 'Program duplicated. Its rules start switched off.');
    }

    /**
     * @return Collection<int, Plan>
     */
    private function plans()
    {
        return Plan::query()->orderBy('sort_order')->get(['id', 'name']);
    }
}
