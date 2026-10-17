<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SuperAdmin\SuperAdmin;
use Illuminate\View\View;

/**
 * Who else holds a super admin account. Accounts are managed with the
 * `super-admin:*` artisan commands, never from the browser.
 */
class TeamController extends Controller
{
    public function index(): View
    {
        return view('super-admin.team.index', [
            'team' => SuperAdmin::query()->orderBy('email')->get(),
        ]);
    }
}
