<?php

namespace App\Services\SuperAdmin;

use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Read-only lookups of workspaces for the super admin panel. Nothing here
 * writes — the current usage period is looked up, never created the way
 * `Workspace::currentUsagePeriod()` would.
 */
class SuperAdminWorkspaceDirectory
{
    private const int PER_PAGE = 25;

    private const int RECENT_LIMIT = 10;

    /**
     * @param  array<string, mixed>  $filters  validated `IndexWorkspacesRequest` input
     * @return LengthAwarePaginator<int, Workspace>
     */
    public function search(array $filters, ?string $pattern): LengthAwarePaginator
    {
        $search = trim((string) ($filters['q'] ?? ''));

        return Workspace::query()
            ->when(($filters['deleted'] ?? null) === 'with', fn (Builder $query) => $query->withTrashed())
            ->when(($filters['deleted'] ?? null) === 'only', fn (Builder $query) => $query->onlyTrashed())
            ->when($pattern !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($pattern, $search): void {
                $query->where('name', 'like', $pattern)
                    ->orWhere('slug', 'like', $pattern)
                    ->when(Str::isUuid($search), fn (Builder $query) => $query->orWhere('id', $search))
                    ->orWhereHas('owner', fn (Builder $owner) => $owner->where('email', 'like', $pattern));
            }))
            ->with('owner:id,name,email')
            ->withCount(['members', 'workflows'])
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Everything the workspace page shows.
     *
     * @return array<string, mixed>
     */
    public function detail(Workspace $workspace): array
    {
        $workspace->load(['owner:id,name,email', 'members.user:id,name,email']);
        $workspace->loadCount(['workflows', 'agents', 'runs']);

        return [
            'workspace' => $workspace,
            'plan' => $workspace->currentPlan(),
            'subscription' => $workspace->subscriptions()->with('plan')->latest()->first(),
            'usagePeriod' => $workspace->usagePeriods()
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>', now())
                ->latest('starts_at')
                ->first(),
            'recentRuns' => $workspace->runs()
                ->whereNull('parent_run_id')
                ->with(['workflow' => fn ($query) => $query->withTrashed()->select('id', 'name')])
                ->latest()
                ->limit(self::RECENT_LIMIT)
                ->get(),
            'creditTransactions' => $workspace->creditTransactions()->latest()->limit(self::RECENT_LIMIT)->get(),
        ];
    }
}
