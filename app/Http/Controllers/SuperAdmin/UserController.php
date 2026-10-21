<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\IndexUsersRequest;
use App\Models\User;
use App\Services\SuperAdmin\SuperAdminUserDirectory;
use Illuminate\View\View;

/**
 * Read-only view of customer accounts for support.
 */
class UserController extends Controller
{
    public function __construct(private readonly SuperAdminUserDirectory $users) {}

    public function index(IndexUsersRequest $request): View
    {
        return view('super-admin.users.index', [
            'users' => $this->users->search($request->validated(), $request->searchPattern()),
            'filters' => $request->validated(),
        ]);
    }

    public function show(User $user): View
    {
        $user->load(['workspaces' => fn ($query) => $query->withTrashed()->orderBy('name'), 'referral']);

        return view('super-admin.users.show', [
            'user' => $user,
            'authEvents' => $user->authEvents()->latest()->limit(20)->get(),
            'superAdminActivity' => $this->users->recentSuperAdminActivity($user),
        ]);
    }
}
