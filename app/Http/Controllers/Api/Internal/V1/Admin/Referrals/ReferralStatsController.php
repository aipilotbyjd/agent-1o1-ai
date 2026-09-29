<?php

namespace App\Http\Controllers\Api\Internal\V1\Admin\Referrals;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Referrals\ReferralStats;
use Illuminate\Http\Request;

class ReferralStatsController extends Controller
{
    public function show(Request $request, ReferralStats $stats)
    {
        $request->validate(['days' => ['nullable', 'integer', 'min:1', 'max:3650']]);

        return ApiResponse::success($stats->platform($request->integer('days', 30)));
    }
}
