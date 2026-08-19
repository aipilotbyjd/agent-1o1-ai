<?php

use App\Actions\Billing\DeductCreditsAction;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\Notifications\AlertSeverity;
use App\Exceptions\InsufficientCreditsException;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Notifications\Admin\AdminAlertNotification;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config()->set('admin_alerts.enabled', true);
    config()->set('admin_alerts.recipients.discord', 'https://discord.test/webhook');
    config()->set('admin_alerts.recipients.mail', ['ops@example.com']);
    config()->set('admin_alerts.usage.threshold_percent', 80);
    config()->set('admin_alerts.throttle_seconds', 3600);
});

function workspaceWithCreditLimit(int $limit): Workspace
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workspace->currentUsagePeriod()->update(['credits_limit' => $limit]);

    return $workspace;
}

it('alerts admins when a charge crosses the usage threshold', function () {
    $workspace = workspaceWithCreditLimit(100);
    Notification::fake();

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 80);

    Notification::assertSentOnDemand(
        AdminAlertNotification::class,
        function (AdminAlertNotification $notification) use ($workspace): bool {
            return $notification->key === 'usage.threshold_crossed'
                && $notification->severity === AlertSeverity::Warning
                && $notification->context === [
                    'workspace_id' => $workspace->id,
                    'credits_used' => 80,
                    'credits_limit' => 100,
                    'percent_used' => 80,
                ];
        },
    );
});

it('stays quiet while usage is below the threshold', function () {
    $workspace = workspaceWithCreditLimit(100);
    Notification::fake();

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 79);

    Notification::assertNothingSent();
});

it('alerts only on the charge that crosses the threshold, not on later ones', function () {
    $workspace = workspaceWithCreditLimit(100);
    Notification::fake();

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 85);
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 2, 5);

    Notification::assertSentOnDemandTimes(AdminAlertNotification::class, 1);
});

it('stays quiet for workspaces on an unlimited plan', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    Notification::fake();

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 10_000);

    Notification::assertNothingSent();
});

it('raises a critical alert when a workspace is blocked by its credit limit', function () {
    $workspace = workspaceWithCreditLimit(10);
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 8);

    Notification::fake();

    expect(fn () => app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 2, 5))
        ->toThrow(InsufficientCreditsException::class);

    Notification::assertSentOnDemand(
        AdminAlertNotification::class,
        function (AdminAlertNotification $notification) use ($workspace): bool {
            return $notification->key === 'usage.limit_exhausted'
                && $notification->severity === AlertSeverity::Critical
                && $notification->context === [
                    'workspace_id' => $workspace->id,
                    'credits_needed' => 5,
                    'credits_available' => 2,
                ];
        },
    );
});

it('still rolls the rejected charge back when the alert is raised', function () {
    $workspace = workspaceWithCreditLimit(10);
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 8);

    Notification::fake();

    expect(fn () => app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 2, 5))
        ->toThrow(InsufficientCreditsException::class);

    expect($workspace->currentUsagePeriod()->credits_used)->toBe(8);
    expect($workspace->creditTransactions()->count())->toBe(1);
});

it('throttles the blocked-workspace alert across repeated rejections', function () {
    $workspace = workspaceWithCreditLimit(10);
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 8);

    Notification::fake();

    foreach ([2, 3, 4] as $sourceId) {
        expect(fn () => app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, $sourceId, 5))
            ->toThrow(InsufficientCreditsException::class);
    }

    Notification::assertSentOnDemandTimes(AdminAlertNotification::class, 1);
});
