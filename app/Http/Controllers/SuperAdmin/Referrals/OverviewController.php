<?php

namespace App\Http\Controllers\SuperAdmin\Referrals;

use App\Http\Controllers\Controller;
use App\Models\Referrals\ReferralProgram;
use App\Services\Referrals\ReferralStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OverviewController extends Controller
{
    public function __invoke(Request $request, ReferralStats $stats): View
    {
        $days = (int) ($request->validate(['days' => ['nullable', 'integer', 'in:7,30,90,365']])['days'] ?? 30);

        return view('super-admin.referrals.overview', [
            'stats' => $stats->platform($days),
            'days' => $days,
            'defaultProgram' => ReferralProgram::query()->where('is_default', true)->first(),
        ]);
    }
}
