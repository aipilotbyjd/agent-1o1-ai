<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\SuperAdminDashboardStats;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(SuperAdminDashboardStats $stats): View
    {
        return view('super-admin.dashboard', [
            'stats' => $stats->overview(),
            'recentActivity' => $stats->recentActivity(),
        ]);
    }
}
