<?php

namespace App\Http\Controllers\SuperAdmin\Referrals;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Referrals\BlockDomainRequest;
use App\Models\Referrals\ReferralBlockedDomain;
use App\Services\Referrals\ReferralAdmin;
use App\Services\SuperAdmin\SuperAdminReferrals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Email domains whose signups never earn referral rewards.
 */
class BlockedDomainController extends Controller
{
    public function __construct(
        private readonly ReferralAdmin $admin,
        private readonly SuperAdminReferrals $referrals,
    ) {}

    public function index(): View
    {
        return view('super-admin.referrals.blocked-domains.index', [
            'domains' => ReferralBlockedDomain::query()->orderBy('domain')->get(),
        ]);
    }

    public function store(BlockDomainRequest $request): RedirectResponse
    {
        $domain = $request->validated('domain');

        $this->referrals->run(
            $request->user('super_admin'),
            'referral_domain.blocked',
            null,
            fn (): ReferralBlockedDomain => $this->admin->blockDomain($domain, $request->validated('reason'), null),
            ['domain' => $domain],
        );

        return back()->with('status', "{$domain} blocked.");
    }

    public function destroy(Request $request, ReferralBlockedDomain $domain): RedirectResponse
    {
        $this->referrals->run($request->user('super_admin'), 'referral_domain.unblocked', $domain, fn () => $this->admin->unblockDomain($domain, null), ['domain' => $domain->domain]);

        return back()->with('status', "{$domain->domain} unblocked.");
    }
}
