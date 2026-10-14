<?php

namespace App\Services\SuperAdmin;

use App\Enums\RunStatus;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\NodeTester;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide numbers for the super admin panel dashboard. Cached for a minute
 * so a refreshing dashboard doesn't count every table on each load.
 *
 * Runs follow `DashboardMetrics`: top-level runs only, builder node tests
 * excluded.
 */
class SuperAdminDashboardStats
{
    private const int CACHE_SECONDS = 60;

    private const int RECENT_ACTIVITY_LIMIT = 10;

    /**
     * @return array{
     *     users_total: int,
     *     users_new_7d: int,
     *     workspaces_total: int,
     *     active_subscriptions: int,
     *     runs_today: int,
     *     runs_failed_today: int,
     *     generated_at: string
     * }
     */
    public function overview(): array
    {
        return Cache::remember('super-admin:dashboard-overview', self::CACHE_SECONDS, function (): array {
            $runsToday = Run::query()
                ->whereNull('parent_run_id')
                ->where('trigger_type', '!=', NodeTester::TRIGGER_TYPE)
                ->where('created_at', '>=', now()->startOfDay());

            return [
                'users_total' => User::query()->count(),
                'users_new_7d' => User::query()->where('created_at', '>=', now()->subDays(7))->count(),
                'workspaces_total' => Workspace::query()->count(),
                'active_subscriptions' => DB::table('subscriptions')->whereIn('stripe_status', ['active', 'trialing'])->count(),
                'runs_today' => (clone $runsToday)->count(),
                'runs_failed_today' => (clone $runsToday)->where('status', RunStatus::Failed)->count(),
                'generated_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * @return Collection<int, SuperAdminAuditLog>
     */
    public function recentActivity(): Collection
    {
        return SuperAdminAuditLog::query()
            ->with(['superAdmin:id,name,email'])
            ->latest('created_at')
            ->limit(self::RECENT_ACTIVITY_LIMIT)
            ->get();
    }
}
