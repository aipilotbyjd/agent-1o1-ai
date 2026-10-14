<?php

use App\Enums\RunStatus;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\SuperAdmin\SuperAdminAuditLogger;
use App\Services\SuperAdmin\SuperAdminDashboardStats;
use App\Services\Workflows\NodeTester;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

it('shows the dashboard to a signed-in super admin', function () {
    $superAdmin = superAdmin(['email' => 'ops@example.com']);
    signInAsSuperAdmin($superAdmin);

    $this->get(route('super-admin.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Recent super admin activity')
        ->assertSee('super_admin.login.succeeded')
        ->assertSee('ops@example.com');
});

it('counts users, workspaces and top-level runs only', function () {
    $completed = Run::factory()->create(['status' => RunStatus::Completed]);
    Run::factory()->forWorkspace($completed->workspace)->create(['status' => RunStatus::Failed]);
    Run::factory()->forWorkspace($completed->workspace)->create(['trigger_type' => NodeTester::TRIGGER_TYPE]);
    Run::factory()->forWorkspace($completed->workspace)->create(['parent_run_id' => $completed->id]);
    Run::factory()->forWorkspace($completed->workspace)->create(['created_at' => now()->subDays(2)]);

    Cache::flush();
    $stats = app(SuperAdminDashboardStats::class)->overview();

    expect($stats['runs_today'])->toBe(2)
        ->and($stats['runs_failed_today'])->toBe(1)
        ->and($stats['users_total'])->toBe(User::query()->count())
        ->and($stats['workspaces_total'])->toBe(Workspace::query()->count());
});

it('names the actor of recent activity', function () {
    $superAdmin = superAdmin(['email' => 'ops@example.com']);

    app(SuperAdminAuditLogger::class)->record($superAdmin, 'example.panel_action');

    signInAsSuperAdmin($superAdmin);

    $this->get(route('super-admin.dashboard'))
        ->assertSee('example.panel_action')
        ->assertSee('ops@example.com');
});
