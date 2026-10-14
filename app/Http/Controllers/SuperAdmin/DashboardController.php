<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\SuperAdminDashboardStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, SuperAdminDashboardStats $stats): View
    {
        $days = in_array($request->integer('days'), SuperAdminDashboardStats::WINDOWS, true)
            ? $request->integer('days')
            : SuperAdminDashboardStats::WINDOWS[0];

        return view('super-admin.dashboard', [
            'days' => $days,
            'windows' => SuperAdminDashboardStats::WINDOWS,
            'stats' => $stats->overview($days),
            'recentRuns' => $stats->recentRuns(),
            'recentUsers' => $stats->recentUsers(),
            'recentActivity' => $stats->recentActivity(),
        ]);
    }
}
