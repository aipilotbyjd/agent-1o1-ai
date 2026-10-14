<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardStats;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(AdminDashboardStats $stats): View
    {
        return view('admin.dashboard', [
            'stats' => $stats->overview(),
            'recentActivity' => $stats->recentActivity(),
        ]);
    }
}
