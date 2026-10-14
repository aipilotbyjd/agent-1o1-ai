<?php

use App\Models\Admin\AdminAuditLog;
use App\Services\Admin\AdminAuditLogger;
use App\Services\SuperAdmin\SuperAdminAuditLogger;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

it('lists entries from super admins and from the command line', function () {
    $superAdmin = superAdmin(['email' => 'ops@example.com']);
    $audit = app(SuperAdminAuditLogger::class);

    $audit->record($superAdmin, 'example.panel_action');
    $audit->record(null, 'example.cli_action');

    signInAsSuperAdmin($superAdmin);

    $this->get(route('super-admin.audit-log.index'))
        ->assertOk()
        ->assertSee('example.panel_action')
        ->assertSee('example.cli_action')
        ->assertSee('System / CLI');
});

it('filters by action prefix and by super admin', function () {
    $superAdmin = superAdmin();
    $other = superAdmin();
    $audit = app(SuperAdminAuditLogger::class);

    $audit->record($superAdmin, 'example.mine');
    $audit->record($other, 'example.theirs');
    $audit->record(null, 'other.cli_action');

    signInAsSuperAdmin($superAdmin);

    $this->get(route('super-admin.audit-log.index', ['action' => 'example.']))
        ->assertSee('example.mine')
        ->assertSee('example.theirs')
        ->assertDontSee('other.cli_action');

    $this->get(route('super-admin.audit-log.index', ['action' => 'example.', 'super_admin' => $other->id]))
        ->assertSee('example.theirs')
        ->assertDontSee('example.mine');
});

it('keeps the platform-admin audit log out of the super admin panel', function () {
    $superAdmin = superAdmin();
    app(AdminAuditLogger::class)->record(platformAdmin(), 'example.platform_action');
    app(SuperAdminAuditLogger::class)->record($superAdmin, 'example.panel_action');

    signInAsSuperAdmin($superAdmin);

    $this->get(route('super-admin.audit-log.index'))
        ->assertSee('example.panel_action')
        ->assertDontSee('example.platform_action');

    expect(AdminAuditLog::query()->pluck('action')->all())->toBe(['example.platform_action']);
});

it('shows super admin sign-in history through the action filter', function () {
    $superAdmin = superAdmin();
    signInAsSuperAdmin($superAdmin);

    $this->get(route('super-admin.audit-log.index', ['action' => 'super_admin.login']))
        ->assertOk()
        ->assertSee('super_admin.login.succeeded');
});

it('treats a wildcard in the action filter as text', function () {
    $superAdmin = superAdmin();
    app(SuperAdminAuditLogger::class)->record($superAdmin, 'example.panel_action');
    signInAsSuperAdmin($superAdmin);

    $this->get(route('super-admin.audit-log.index', ['action' => '%']))->assertOk()->assertDontSee('example.panel_action');
});

it('escapes recorded details when showing them', function () {
    $superAdmin = superAdmin();
    app(SuperAdminAuditLogger::class)->record($superAdmin, 'example.xss', null, null, ['name' => '<script>alert(1)</script>']);
    signInAsSuperAdmin($superAdmin);

    $this->get(route('super-admin.audit-log.index'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false);
});
