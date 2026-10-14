<?php

use App\Services\Admin\AdminAuditLogger;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

it('lists audit entries from panel admins, platform admins and the CLI', function () {
    $panel = panelAdmin(['email' => 'panel@example.com']);
    $platform = platformAdmin();
    $audit = app(AdminAuditLogger::class);

    $audit->record($panel, 'example.panel_action');
    $audit->record($platform, 'example.platform_action');
    $audit->record(null, 'example.cli_action');

    signInToPanel($panel);

    $this->get(route('panel.audit-log.index'))
        ->assertOk()
        ->assertSee('example.panel_action')
        ->assertSee('example.platform_action')
        ->assertSee('example.cli_action')
        ->assertSee('System / CLI');
});

it('filters by action prefix and actor', function () {
    $panel = panelAdmin();
    $audit = app(AdminAuditLogger::class);

    $audit->record($panel, 'example.panel_action');
    $audit->record(platformAdmin(), 'example.platform_action');
    $audit->record(null, 'other.cli_action');

    signInToPanel($panel);

    $this->get(route('panel.audit-log.index', ['action' => 'example.']))
        ->assertSee('example.panel_action')
        ->assertSee('example.platform_action')
        ->assertDontSee('other.cli_action');

    $this->get(route('panel.audit-log.index', ['actor' => 'platform']))
        ->assertSee('example.platform_action')
        ->assertDontSee('example.panel_action');

    $this->get(route('panel.audit-log.index', ['actor' => 'system']))
        ->assertSee('other.cli_action')
        ->assertDontSee('example.platform_action');
});

it('shows admin sign-in history through the action filter', function () {
    $admin = panelAdmin();
    signInToPanel($admin);

    $this->get(route('panel.audit-log.index', ['action' => 'admin.login']))
        ->assertOk()
        ->assertSee('admin.login.succeeded');
});

it('treats a wildcard in the action filter as text', function () {
    $admin = panelAdmin();
    app(AdminAuditLogger::class)->record($admin, 'example.panel_action');
    signInToPanel($admin);

    $this->get(route('panel.audit-log.index', ['action' => '%']))->assertOk()->assertDontSee('example.panel_action');
});

it('escapes recorded details when showing them', function () {
    $admin = panelAdmin();
    app(AdminAuditLogger::class)->record($admin, 'example.xss', null, null, ['name' => '<script>alert(1)</script>']);
    signInToPanel($admin);

    $this->get(route('panel.audit-log.index'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false);
});
