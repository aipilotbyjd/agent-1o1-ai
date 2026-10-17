<?php

namespace App\Http\Controllers\SuperAdmin\Referrals;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Referrals\UpdateCodeRequest;
use App\Models\Referrals\ReferralCode;
use App\Models\Referrals\ReferralProgram;
use App\Services\Referrals\ReferralAdmin;
use App\Services\SuperAdmin\SuperAdminReferrals;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CodeController extends Controller
{
    private const int PER_PAGE = 50;

    public function __construct(
        private readonly ReferralAdmin $admin,
        private readonly SuperAdminReferrals $referrals,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255']]);
        $term = trim((string) ($filters['q'] ?? ''));
        $pattern = '%'.addcslashes($term, '%_\\').'%';

        return view('super-admin.referrals.codes.index', [
            'codes' => ReferralCode::query()
                ->with(['user:id,name,email', 'program:id,name'])
                ->withCount(['referrals', 'visits'])
                ->when($term !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($pattern): void {
                    $query->where('code', 'like', $pattern)
                        ->orWhereHas('user', fn (Builder $user) => $user->where('email', 'like', $pattern)->orWhere('name', 'like', $pattern));
                }))
                ->latest()
                ->paginate(self::PER_PAGE)
                ->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function edit(ReferralCode $code): View
    {
        $code->load(['user:id,name,email', 'user.ownedWorkspaces:id,name,owner_id']);

        return view('super-admin.referrals.codes.edit', [
            'code' => $code,
            'programs' => ReferralProgram::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateCodeRequest $request, ReferralCode $code): RedirectResponse
    {
        $this->referrals->run(
            $request->user('super_admin'),
            'referral_code.updated',
            $code,
            fn (): ReferralCode => $this->admin->updateCode($code, $request->validated(), null),
            ['fields' => array_keys($request->validated())],
        );

        return redirect()->route('super-admin.referrals.codes.index', ['q' => $code->refresh()->code])->with('status', 'Referral code saved.');
    }
}
