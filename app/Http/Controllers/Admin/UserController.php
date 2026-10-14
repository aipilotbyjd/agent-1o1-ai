<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexUsersRequest;
use App\Models\Admin\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Read-only view of customer accounts for support.
 */
class UserController extends Controller
{
    private const int PER_PAGE = 25;

    public function index(IndexUsersRequest $request): View
    {
        $pattern = $request->searchPattern();
        $search = trim((string) $request->validated('q'));

        $users = User::query()
            ->when($pattern !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($pattern, $search): void {
                $query->where('email', 'like', $pattern)
                    ->orWhere('name', 'like', $pattern)
                    ->when(Str::isUuid($search), fn (Builder $query) => $query->orWhere('id', $search));
            }))
            ->when($request->validated('two_factor') === 'enabled', fn (Builder $query) => $query->whereNotNull('two_factor_confirmed_at'))
            ->when($request->validated('two_factor') === 'disabled', fn (Builder $query) => $query->whereNull('two_factor_confirmed_at'))
            ->when($request->validated('verified') === 'yes', fn (Builder $query) => $query->whereNotNull('email_verified_at'))
            ->when($request->validated('verified') === 'no', fn (Builder $query) => $query->whereNull('email_verified_at'))
            ->withCount('workspaces')
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $request->validated(),
        ]);
    }

    public function show(User $user): View
    {
        $user->load(['workspaces' => fn ($query) => $query->withTrashed()->orderBy('name'), 'referral']);

        return view('admin.users.show', [
            'user' => $user,
            'authEvents' => $user->authEvents()->latest()->limit(20)->get(),
            'adminActivity' => AdminAuditLog::query()
                ->with(['admin:id,email', 'panelAdmin:id,email'])
                ->where('subject_id', $user->id)
                ->latest('created_at')
                ->limit(10)
                ->get(),
        ]);
    }
}
