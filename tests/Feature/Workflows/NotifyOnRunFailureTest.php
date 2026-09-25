<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\RunStatus;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
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

it('names what failed instead of the raw run id', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create(['name' => 'Lead sync']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['name' => 'Support Bot']);
    $session = AgentSession::factory()->forAgent($agent)->create();

    $workflowRun = Run::factory()->create(['runnable_type' => Workflow::class, 'runnable_id' => $workflow->id, 'error' => 'Timed out.']);
    $chatRun = $session->runs()->create(['workspace_id' => $workspace->id, 'trigger_type' => 'manual']);
    $chatRun->forceFill(['error' => null])->save();

    $workflowNotice = new RunFailedNotification($workspace, $workflowRun);
    $chatNotice = new RunFailedNotification($workspace, $chatRun);

    expect($workflowNotice->title)->toBe('Workflow “Lead sync” failed');
    expect($workflowNotice->body)->toBe('Timed out.');
    expect($chatNotice->title)->toBe('“Support Bot” couldn\'t reply');
    expect($chatNotice->body)->toBe('No error details were recorded.');
});

it('still says what kind of run failed after its chat is deleted', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = AgentSession::factory()->forAgent(Agent::factory()->forWorkspace($workspace)->create())->create();
    $run = $session->runs()->create(['workspace_id' => $workspace->id, 'trigger_type' => 'manual']);
    $session->forceDelete();

    expect((new RunFailedNotification($workspace, $run->fresh()))->title)->toBe("An agent couldn't reply");
});
