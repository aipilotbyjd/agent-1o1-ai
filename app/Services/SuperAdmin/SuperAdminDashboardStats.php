<?php

namespace App\Services\SuperAdmin;

use App\Enums\RunStatus;
use App\Models\Billing\CreditTransaction;
use App\Models\Runs\Run;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Dashboard\DashboardMetrics;
use App\Services\Dashboard\DashboardWindow;
use App\Services\Workflows\NodeTester;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide numbers for the super admin panel dashboard. Cached for a minute
 * per window so a refreshing dashboard doesn't count every table on each load.
 *
 * Runs follow `DashboardMetrics`: top-level runs only, builder node tests
 * excluded. Every "previous" figure is the window of the same length right
 * before the selected one.
 */
class SuperAdminDashboardStats
{
    /**
     * The windows the dashboard offers, in days. The first is the default.
     */
    public const array WINDOWS = [30, 7, 90];

    private const int CACHE_SECONDS = 60;

    private const int RECENT_LIMIT = 6;

    private const int TOP_WORKSPACES_LIMIT = 5;

    /**
     * @return array{
     *     window: array{days: int, starts_at: string, ends_at: string},
     *     users: array{total: int, new: int, new_previous: int},
     *     workspaces: array{total: int, new: int, new_previous: int},
     *     subscriptions: array{active: int, trialing: int, past_due: int},
     *     runs: array{total: int, previous_total: int, completed: int, failed: int, cancelled: int, success_rate: float|null, avg_duration_ms: int|null},
     *     in_flight: array<string, int>,
     *     credits: array{used: int, used_previous: int},
     *     run_series: array<int, array{date: string, total: int, completed: int, failed: int}>,
     *     signup_series: array<int, array{date: string, total: int}>,
     *     top_workspaces: array<int, array{id: string, name: string, runs: int, failed: int}>,
     *     generated_at: string
     * }
     */
    public function overview(int $days): array
    {
        return Cache::remember("super-admin:dashboard-overview:{$days}", self::CACHE_SECONDS, function () use ($days): array {
            $window = DashboardWindow::ofDays($days);
            $previousFrom = $window->from->subDays($days);

            return [
                'window' => $window->toArray(),
                'users' => [
                    'total' => User::query()->count(),
                    'new' => User::query()->where('created_at', '>=', $window->from)->count(),
                    'new_previous' => User::query()->where('created_at', '>=', $previousFrom)->where('created_at', '<', $window->from)->count(),
                ],
                'workspaces' => [
                    'total' => Workspace::query()->count(),
                    'new' => Workspace::query()->where('created_at', '>=', $window->from)->count(),
                    'new_previous' => Workspace::query()->where('created_at', '>=', $previousFrom)->where('created_at', '<', $window->from)->count(),
                ],
                'subscriptions' => $this->subscriptionCounts(),
                'runs' => $this->runTotals($window, $previousFrom),
                'in_flight' => $this->inFlight(),
                'credits' => [
                    'used' => (int) CreditTransaction::query()->where('created_at', '>=', $window->from)->sum('credits'),
                    'used_previous' => (int) CreditTransaction::query()->where('created_at', '>=', $previousFrom)->where('created_at', '<', $window->from)->sum('credits'),
                ],
                'run_series' => $this->runSeries($window),
                'signup_series' => $this->signupSeries($window),
                'top_workspaces' => $this->topWorkspaces($window),
                'generated_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * @return Collection<int, Run>
     */
    public function recentRuns(): Collection
    {
        return $this->runs()
            ->with(['workflow:id,name', 'workspace:id,name'])
            ->latest('created_at')
            ->limit(self::RECENT_LIMIT)
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function recentUsers(): Collection
    {
        return User::query()
            ->withCount('workspaces')
            ->latest('created_at')
            ->limit(self::RECENT_LIMIT)
            ->get();
    }

    /**
     * @return Collection<int, SuperAdminAuditLog>
     */
    public function recentActivity(): Collection
    {
        return SuperAdminAuditLog::query()
            ->with(['superAdmin:id,name,email'])
            ->latest('created_at')
            ->limit(self::RECENT_LIMIT)
            ->get();
    }

    /**
     * @return array{active: int, trialing: int, past_due: int}
     */
    private function subscriptionCounts(): array
    {
        $counts = DB::table('subscriptions')
            ->whereIn('stripe_status', ['active', 'trialing', 'past_due'])
            ->select('stripe_status')
            ->selectRaw('count(*) as subscriptions_count')
            ->groupBy('stripe_status')
            ->pluck('subscriptions_count', 'stripe_status');

        return [
            'active' => (int) $counts->get('active', 0),
            'trialing' => (int) $counts->get('trialing', 0),
            'past_due' => (int) $counts->get('past_due', 0),
        ];
    }

    /**
     * `success_rate` is over finished runs only, as in `DashboardMetrics`.
     *
     * @return array{total: int, previous_total: int, completed: int, failed: int, cancelled: int, success_rate: float|null, avg_duration_ms: int|null}
     */
    private function runTotals(DashboardWindow $window, CarbonImmutable $previousFrom): array
    {
        $byStatus = $this->runs()
            ->where('runs.created_at', '>=', $window->from)
            ->toBase()
            ->select('status')
            ->selectRaw('count(*) as runs_count')
            ->groupBy('status')
            ->pluck('runs_count', 'status');

        $completed = (int) $byStatus->get(RunStatus::Completed->value, 0);
        $failed = (int) $byStatus->get(RunStatus::Failed->value, 0);
        $cancelled = (int) $byStatus->get(RunStatus::Cancelled->value, 0);
        $finished = $completed + $failed + $cancelled;

        return [
            'total' => (int) $byStatus->sum(),
            'previous_total' => $this->runs()->where('runs.created_at', '>=', $previousFrom)->where('runs.created_at', '<', $window->from)->count(),
            'completed' => $completed,
            'failed' => $failed,
            'cancelled' => $cancelled,
            'success_rate' => $finished === 0 ? null : round($completed / $finished, 4),
            'avg_duration_ms' => $this->averageDurationMs($window),
        ];
    }

    /**
     * Runs still in flight right now, whatever window is selected.
     *
     * @return array<string, int>
     */
    private function inFlight(): array
    {
        $byStatus = $this->runs()
            ->whereIn('status', RunStatus::inFlight())
            ->toBase()
            ->select('status')
            ->selectRaw('count(*) as runs_count')
            ->groupBy('status')
            ->pluck('runs_count', 'status');

        $counts = [];

        foreach (RunStatus::inFlight() as $status) {
            $counts[$status->value] = (int) $byStatus->get($status->value, 0);
        }

        return $counts;
    }

    private function averageDurationMs(DashboardWindow $window): ?int
    {
        $expression = DashboardMetrics::durationMs('runs.started_at', 'runs.finished_at');

        if ($expression === null) {
            return null;
        }

        $average = $this->runs()
            ->where('runs.created_at', '>=', $window->from)
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->toBase()
            ->selectRaw("avg({$expression}) as duration")
            ->value('duration');

        return $average === null ? null : (int) round((float) $average);
    }

    /**
     * @return array<int, array{date: string, total: int, completed: int, failed: int}>
     */
    private function runSeries(DashboardWindow $window): array
    {
        $bucket = DashboardMetrics::dayBucket('runs.created_at');

        $rows = $this->runs()
            ->where('runs.created_at', '>=', $window->from)
            ->toBase()
            ->selectRaw("{$bucket} as bucket")
            ->selectRaw('count(*) as runs_count')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as completed_count', [RunStatus::Completed->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as failed_count', [RunStatus::Failed->value])
            ->groupByRaw($bucket)
            ->get()
            ->keyBy('bucket');

        return array_map(fn (string $date): array => [
            'date' => $date,
            'total' => (int) ($rows->get($date)->runs_count ?? 0),
            'completed' => (int) ($rows->get($date)->completed_count ?? 0),
            'failed' => (int) ($rows->get($date)->failed_count ?? 0),
        ], $window->dateKeys());
    }

    /**
     * @return array<int, array{date: string, total: int}>
     */
    private function signupSeries(DashboardWindow $window): array
    {
        $bucket = DashboardMetrics::dayBucket('users.created_at');

        $rows = User::query()
            ->where('users.created_at', '>=', $window->from)
            ->toBase()
            ->selectRaw("{$bucket} as bucket")
            ->selectRaw('count(*) as users_count')
            ->groupByRaw($bucket)
            ->pluck('users_count', 'bucket');

        return array_map(fn (string $date): array => [
            'date' => $date,
            'total' => (int) $rows->get($date, 0),
        ], $window->dateKeys());
    }

    /**
     * Busiest workspaces by runs in the window. Deleted workspaces still own
     * their runs, so they keep their name here.
     *
     * @return array<int, array{id: string, name: string, runs: int, failed: int}>
     */
    private function topWorkspaces(DashboardWindow $window): array
    {
        $rows = $this->runs()
            ->where('runs.created_at', '>=', $window->from)
            ->toBase()
            ->select('workspace_id')
            ->selectRaw('count(*) as runs_count')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as failed_count', [RunStatus::Failed->value])
            ->groupBy('workspace_id')
            ->orderByDesc('runs_count')
            ->limit(self::TOP_WORKSPACES_LIMIT)
            ->get();

        $names = Workspace::query()
            ->withTrashed()
            ->whereIn('id', $rows->pluck('workspace_id'))
            ->pluck('name', 'id');

        return $rows->map(fn (object $row): array => [
            'id' => (string) $row->workspace_id,
            'name' => (string) $names->get($row->workspace_id, 'Unknown workspace'),
            'runs' => (int) $row->runs_count,
            'failed' => (int) $row->failed_count,
        ])->all();
    }

    /**
     * @return Builder<Run>
     */
    private function runs(): Builder
    {
        return Run::query()
            ->whereNull('runs.parent_run_id')
            ->where('runs.trigger_type', '!=', NodeTester::TRIGGER_TYPE);
    }
}
