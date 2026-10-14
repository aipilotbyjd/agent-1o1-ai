<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexWorkspacesRequest;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Read-only view of workspaces: plan, credits, members and recent runs.
 * Nothing here writes — the current usage period is looked up, never
 * created the way `Workspace::currentUsagePeriod()` would.
 */
class WorkspaceController extends Controller
{
    private const int PER_PAGE = 25;

    private const int RECENT_LIMIT = 10;

    public function index(IndexWorkspacesRequest $request): View
    {
        $pattern = $request->searchPattern();
        $search = trim((string) $request->validated('q'));

        $workspaces = Workspace::query()
            ->when($request->validated('deleted') === 'with', fn (Builder $query) => $query->withTrashed())
            ->when($request->validated('deleted') === 'only', fn (Builder $query) => $query->onlyTrashed())
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

        return view('admin.workspaces.index', [
            'workspaces' => $workspaces,
            'filters' => $request->validated(),
        ]);
    }

    public function show(Workspace $workspace): View
    {
        $workspace->load(['owner:id,name,email', 'members.user:id,name,email']);
        $workspace->loadCount(['workflows', 'agents', 'runs']);

        return view('admin.workspaces.show', [
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
        ]);
    }
}
