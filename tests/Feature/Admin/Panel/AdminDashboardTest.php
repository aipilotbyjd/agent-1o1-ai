<?php

use App\Enums\RunStatus;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Admin\AdminDashboardStats;
use App\Services\Workflows\NodeTester;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

it('shows the dashboard to a signed-in admin', function () {
    $admin = panelAdmin(['email' => 'ops@example.com']);
    signInToPanel($admin);

    $this->get(route('panel.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Recent admin activity')
        ->assertSee('admin.login.succeeded')
        ->assertSee('ops@example.com');
});

it('counts users, workspaces and top-level runs only', function () {
    $completed = Run::factory()->create(['status' => RunStatus::Completed]);
    Run::factory()->forWorkspace($completed->workspace)->create(['status' => RunStatus::Failed]);
    Run::factory()->forWorkspace($completed->workspace)->create(['trigger_type' => NodeTester::TRIGGER_TYPE]);
    Run::factory()->forWorkspace($completed->workspace)->create(['parent_run_id' => $completed->id]);
    Run::factory()->forWorkspace($completed->workspace)->create(['created_at' => now()->subDays(2)]);

    Cache::flush();
    $stats = app(AdminDashboardStats::class)->overview();

    expect($stats['runs_today'])->toBe(2)
        ->and($stats['runs_failed_today'])->toBe(1)
        ->and($stats['users_total'])->toBe(User::query()->count())
        ->and($stats['workspaces_total'])->toBe(Workspace::query()->count());
});

it('names the actor for both admin panel and platform-admin entries', function () {
    $panel = panelAdmin(['email' => 'panel@example.com']);
    $platform = platformAdmin();

    app(AdminAuditLogger::class)->record($panel, 'admin.example.panel');
    app(AdminAuditLogger::class)->record($platform, 'admin.example.platform');

    signInToPanel($panel);

    $this->get(route('panel.dashboard'))
        ->assertSee('admin.example.panel')
        ->assertSee('admin.example.platform')
        ->assertSee($platform->email);
});
