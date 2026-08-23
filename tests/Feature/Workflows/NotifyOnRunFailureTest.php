<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\RunStatus;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Notifications\Workspace\RunFailedNotification;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Notification;

it('notifies workspace owners and admins when a run fails', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => 'call_api', 'config' => ['method' => 'GET', 'url' => 'http://127.0.0.1:1/unreachable', 'timeout_seconds' => 1]],
        ],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);

    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh(), []);
    $run = $run->fresh();

    expect($run->status)->toBe(RunStatus::Failed);

    Notification::assertSentTo(
        $owner,
        RunFailedNotification::class,
        fn (RunFailedNotification $n): bool => $n->data['run_id'] === $run->id,
    );
});
