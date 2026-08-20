<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Broadcasting\Channels;
use App\Broadcasting\WorkspaceChannelGate;
use App\Enums\Workspaces\Permission;
use App\Enums\Workspaces\Role;
use App\Events\Runs\NodeRunStateChanged;
use App\Events\Runs\RunStateChanged;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Event;

it('broadcasts run and node-run state changes while a workflow executes', function () {
    Event::fake([RunStateChanged::class, NodeRunStateChanged::class]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['x' => 'input.x']]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['x' => 1]);

    Event::assertDispatched(
        RunStateChanged::class,
        fn (RunStateChanged $event) => $event->run->id === $run->id,
    );
    Event::assertDispatched(
        NodeRunStateChanged::class,
        fn (NodeRunStateChanged $event) => $event->nodeRun->key === 'a',
    );
});

it('puts run events on the workspace and per-run private channels', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $channels = array_map(fn ($channel) => (string) $channel, (new RunStateChanged($run))->broadcastOn());

    expect($channels)->toBe([
        'private-'.Channels::workspaceRuns($workspace->id),
        'private-'.Channels::run($run),
    ]);

    $payload = (new RunStateChanged($run))->broadcastWith();
    expect($payload['id'])->toBe($run->id);
    expect($payload['status'])->toBe('completed');
});

it('keeps node-run payloads off the workspace-wide channel and free of output', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['x' => 'input.x']]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['x' => 'secretish']);
    $nodeRun = $run->fresh(['nodeRuns'])->nodeRuns->first();

    $event = new NodeRunStateChanged($nodeRun);

    expect(array_map(fn ($channel) => (string) $channel, $event->broadcastOn()))
        ->toBe(['private-'.Channels::run($run)]);
    expect($event->broadcastWith())->not->toHaveKey('output');
    expect($event->broadcastAs())->toBe('node-run.state-changed');
});

it('authorizes run channels only for workspace members who can view runs', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh());

    $gate = app(WorkspaceChannelGate::class);

    expect($gate->runs($owner, $workspace->id))->toBeTrue();
    expect($gate->run($owner, $workspace->id, $run->id))->toBeTrue();
    expect($gate->runs($stranger, $workspace->id))->toBeFalse();
    expect($gate->run($stranger, $workspace->id, $run->id))->toBeFalse();
});

it('refuses a run channel whose run belongs to another workspace', function () {
    $owner = User::factory()->create();
    $first = app(WorkspaceService::class)->create($owner, ['name' => 'First']);
    $second = app(WorkspaceService::class)->create($owner, ['name' => 'Second']);

    $workflow = Workflow::factory()->forWorkspace($second)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh());

    expect(app(WorkspaceChannelGate::class)->run($owner, $first->id, $run->id))->toBeFalse();
});

it('authorizes agent session channels for members and refuses outsiders', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = AgentSession::factory()->forAgent($agent)->create();

    $gate = app(WorkspaceChannelGate::class);

    expect($gate->agentSession($owner, $workspace->id, $session->id))->toBeTrue();
    expect($gate->agentSession($stranger, $workspace->id, $session->id))->toBeFalse();
    expect(Role::Viewer->has(Permission::AgentView))->toBeTrue();
});
