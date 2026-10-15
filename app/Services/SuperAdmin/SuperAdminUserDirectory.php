<?php

namespace App\Services\SuperAdmin;

use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Read-only lookups of customer accounts for the super admin panel.
 */
class SuperAdminUserDirectory
{
    private const int PER_PAGE = 25;

    /**
     * @param  array<string, mixed>  $filters  validated `IndexUsersRequest` input
     * @return LengthAwarePaginator<int, User>
     */
    public function search(array $filters, ?string $pattern): LengthAwarePaginator
    {
        $search = trim((string) ($filters['q'] ?? ''));

        return User::query()
            ->when($pattern !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($pattern, $search): void {
                $query->where('email', 'like', $pattern)
                    ->orWhere('name', 'like', $pattern)
                    ->when(Str::isUuid($search), fn (Builder $query) => $query->orWhere('id', $search));
            }))
            ->when(($filters['two_factor'] ?? null) === 'enabled', fn (Builder $query) => $query->whereNotNull('two_factor_confirmed_at'))
            ->when(($filters['two_factor'] ?? null) === 'disabled', fn (Builder $query) => $query->whereNull('two_factor_confirmed_at'))
            ->when(($filters['verified'] ?? null) === 'yes', fn (Builder $query) => $query->whereNotNull('email_verified_at'))
            ->when(($filters['verified'] ?? null) === 'no', fn (Builder $query) => $query->whereNull('email_verified_at'))
            ->when(($filters['suspended'] ?? null) === 'yes', fn (Builder $query) => $query->whereNotNull('suspended_at'))
            ->when(($filters['suspended'] ?? null) === 'no', fn (Builder $query) => $query->whereNull('suspended_at'))
            ->withCount('workspaces')
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * What the super admins did to this user, newest first.
     *
     * @return Collection<int, SuperAdminAuditLog>
     */
    public function recentSuperAdminActivity(User $user): Collection
    {
        return SuperAdminAuditLog::query()
            ->with(['superAdmin:id,email'])
            ->where('subject_id', $user->id)
            ->latest('created_at')
            ->limit(10)
            ->get();
    }
}
