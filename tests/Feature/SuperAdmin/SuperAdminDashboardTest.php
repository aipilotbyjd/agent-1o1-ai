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

    Run::factory()->forWorkspace($completed->workspace)->create(['created_at' => now()->subDays(10)]);

    Cache::flush();
    $stats = app(SuperAdminDashboardStats::class)->overview(7);

    expect($stats['runs']['total'])->toBe(3)
        ->and($stats['runs']['failed'])->toBe(1)
        ->and($stats['runs']['previous_total'])->toBe(1)
        ->and($stats['run_series'])->toHaveCount(7)
        ->and(array_sum(array_column($stats['run_series'], 'total')))->toBe(3)
        ->and($stats['top_workspaces'][0]['runs'])->toBe(3)
        ->and($stats['users']['total'])->toBe(User::query()->count())
        ->and($stats['workspaces']['total'])->toBe(Workspace::query()->count());
});

it('switches the dashboard window and ignores unknown ones', function () {
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.dashboard', ['days' => 7]))
        ->assertOk()
        ->assertViewHas('days', 7)
        ->assertSee('Platform health over the last 7 days');

    $this->get(route('super-admin.dashboard', ['days' => 365]))
        ->assertOk()
        ->assertViewHas('days', 30);
});

it('names the actor of recent activity', function () {
    $superAdmin = superAdmin(['email' => 'ops@example.com']);

    app(SuperAdminAuditLogger::class)->record($superAdmin, 'example.panel_action');

    signInAsSuperAdmin($superAdmin);

    $this->get(route('super-admin.dashboard'))
        ->assertSee('example.panel_action')
        ->assertSee('ops@example.com');
});
