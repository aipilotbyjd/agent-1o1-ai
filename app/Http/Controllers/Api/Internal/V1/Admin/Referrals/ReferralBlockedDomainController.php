<?php

namespace App\Http\Controllers\Api\Internal\V1\Admin\Referrals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Admin\Referrals\StoreReferralBlockedDomainRequest;
use App\Http\Resources\Api\Internal\V1\Admin\ReferralBlockedDomainResource;
use App\Http\Responses\ApiResponse;
use App\Models\Referrals\ReferralBlockedDomain;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Http\Request;

class ReferralBlockedDomainController extends Controller
{
    public function __construct(private readonly AdminAuditLogger $audit) {}

    public function index()
    {
        return ApiResponse::success(['domains' => ReferralBlockedDomainResource::collection(ReferralBlockedDomain::query()->orderBy('domain')->get())]);
    }

    public function store(StoreReferralBlockedDomainRequest $request)
    {
        $domain = ReferralBlockedDomain::query()->create($request->validated());

        $this->audit->record($request->user(), 'referral_blocked_domain.created', $domain, null, $domain->only(['domain', 'reason']));

        return ApiResponse::created(['domain' => ReferralBlockedDomainResource::make($domain)], 'Domain blocked.');
    }

    public function destroy(Request $request, ReferralBlockedDomain $domain)
    {
        $domain->delete();

        $this->audit->record($request->user(), 'referral_blocked_domain.deleted', $domain, $domain->only(['domain', 'reason']));

        return ApiResponse::success(null, 'Domain unblocked.');
    }
}
