<?php

use App\Enums\NodeRunStatus;
use App\Enums\Notifications\NotificationEvent;
use App\Enums\RunStatus;
use App\Enums\Workspaces\Role;
use App\Events\Runs\RunFailed;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Notifications\Workspace\RunFailedNotification;
use App\Services\Workflows\NodeTester;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Notification;

function failingRunFixture(array $attributes = []): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create(['name' => 'Nightly sync']);

    $run = $workflow->runs()->create([
        'workspace_id' => $workspace->id,
        'runnable_type' => Workflow::class,
        'runnable_id' => $workflow->id,
        'workflow_id' => $workflow->id,
        'trigger_type' => 'manual',
        'input' => [],
        ...$attributes,
    ]);

    $run->forceFill(['status' => RunStatus::Failed, 'error' => 'Slack API error [chat.postMessage]'])->save();

    return [$owner, $workspace, $run];
}

it('notifies workspace owners and admins that a run failed', function () {
    Notification::fake();

    [$owner, $workspace, $run] = failingRunFixture();

    $admin = User::factory()->create();
    $workspace->users()->attach($admin, ['role' => Role::Admin->value]);

    $member = User::factory()->create();
    $workspace->users()->attach($member, ['role' => Role::Member->value]);

    event(new RunFailed($run));

    Notification::assertSentTo([$owner, $admin], RunFailedNotification::class);
    Notification::assertNotSentTo($member, RunFailedNotification::class);
});

it('carries the workflow name, error and run id on the notification', function () {
    Notification::fake();

    [$owner, $workspace, $run] = failingRunFixture();

    event(new RunFailed($run));

    Notification::assertSentTo($owner, RunFailedNotification::class, function (RunFailedNotification $notification) use ($run) {
        return $notification->event === NotificationEvent::RunFailed
            && str_contains($notification->title, 'Nightly sync')
            && $notification->body === 'Slack API error [chat.postMessage]'
            && $notification->data['run_id'] === $run->id;
    });
});

it('stays silent for a child run, since the parent reports the same failure', function () {
    Notification::fake();

    [, , $parent] = failingRunFixture();
    [$owner, , $child] = failingRunFixture(['parent_run_id' => $parent->id]);

    event(new RunFailed($child));

    // Otherwise a 50-iteration Loop would fire 51 notifications for one
    // user-visible failure.
    Notification::assertNothingSentTo($owner);
});

it('stays silent when a single-node test fails in the editor', function () {
    Notification::fake();

    [$owner, , $run] = failingRunFixture(['trigger_type' => NodeTester::TRIGGER_TYPE]);

    event(new RunFailed($run));

    Notification::assertNothingSentTo($owner);
});

it('still notifies when the run failed before any node ran', function () {
    Notification::fake();

    [$owner, , $run] = failingRunFixture();

    expect($run->nodeRuns()->count())->toBe(0);

    event(new RunFailed($run));

    Notification::assertSentTo($owner, RunFailedNotification::class, function (RunFailedNotification $notification) {
        return $notification->data['node_key'] === null;
    });
});

it('names the node the run died on when there is one', function () {
    Notification::fake();

    [$owner, , $run] = failingRunFixture();

    $run->nodeRuns()->create([
        'key' => 'post_to_slack',
        'type' => 'slack_post_message',
        'input' => [],
    ])->forceFill([
        'status' => NodeRunStatus::Failed,
        'finished_at' => now(),
    ])->save();

    event(new RunFailed($run));

    Notification::assertSentTo($owner, RunFailedNotification::class, function (RunFailedNotification $notification) {
        return $notification->data['node_key'] === 'post_to_slack';
    });
});

it('lists run.failed in the toggleable notification event catalogue', function () {
    expect(collect(NotificationEvent::catalog())->pluck('key'))->toContain('run.failed');
});

it('respects a member preference that switches the run.failed channels', function () {
    Notification::fake();

    [$owner, $workspace, $run] = failingRunFixture();

    $workspace->notificationPreferences()->create([
        'user_id' => $owner->id,
        'event_key' => NotificationEvent::RunFailed->value,
        'in_app' => false,
        'email' => true,
    ]);

    event(new RunFailed($run));

    Notification::assertSentTo($owner, RunFailedNotification::class, function (RunFailedNotification $notification, array $channels) {
        return in_array('mail', $channels, true) && ! in_array('database', $channels, true);
    });
});
