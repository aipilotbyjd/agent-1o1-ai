<?php

use App\Enums\Workflows\BuilderSessionStatus;
use App\Jobs\Workflows\ProcessWorkflowBuilderMessageJob;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForBuilderSession(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

it('creates a session with an empty draft graph', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions", [
        'title' => 'New automation',
    ]);

    $response->assertCreated();
    expect($response->json('data.session.title'))->toBe('New automation');
    expect($response->json('data.session.draft_graph'))->toBe(['nodes' => [], 'edges' => []]);
});

it('seeds the draft graph from an existing workflow', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ]);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions", [
        'workflow_id' => $workflow->id,
    ]);

    $response->assertCreated();
    expect($response->json('data.session.draft_graph.nodes'))->toHaveCount(1);
    expect($response->json('data.session.draft_graph.nodes.0.key'))->toBe('a');
});

it('404s reading a session that belongs to a different workspace', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    [$otherWorkspace] = ownerWorkspaceForBuilderSession();

    $foreign = WorkflowBuilderSession::factory()->forWorkspace($otherWorkspace, $owner)->create();

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$foreign->id}")->assertNotFound();
});

it('promotes a draft to a new workflow', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create([
        'draft_graph' => [
            'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
            'edges' => [],
        ],
    ]);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/promote", [
        'name' => 'Published Workflow',
    ]);

    $response->assertOk();
    expect($response->json('data.workflow.name'))->toBe('Published Workflow');
    expect($response->json('data.workflow.nodes'))->toHaveCount(1);
    expect($session->fresh()->workflow_id)->toBe($response->json('data.workflow.id'));
    expect($session->fresh()->status)->toBe(BuilderSessionStatus::Promoted);
});

it('deletes a session', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    Passport::actingAs($owner);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}")->assertNoContent();
    expect(WorkflowBuilderSession::find($session->id))->toBeNull();
});

it('renames and archives a session, and hides archived sessions from the default list', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();
    $base = "/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions";

    Passport::actingAs($owner);

    $this->patchJson("{$base}/{$session->id}", ['title' => 'Lead Router'])
        ->assertOk()
        ->assertJsonPath('data.session.title', 'Lead Router');

    $this->patchJson("{$base}/{$session->id}", ['status' => 'archived'])->assertOk();

    expect($this->getJson($base)->json('data.sessions'))->toBe([]);
    expect($this->getJson("{$base}?status=archived")->json('data.sessions.*.id'))->toBe([$session->id]);
});

it('refuses to set a session to promoted directly', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}", ['status' => 'promoted'])
        ->assertUnprocessable();
});

it('syncs the draft from the canvas', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/draft", [
        'draft_lock_version' => 0,
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []], 'position' => ['x' => 10, 'y' => 20]]],
        'edges' => [],
    ])
        ->assertOk()
        ->assertJsonPath('data.session.draft_lock_version', 1)
        ->assertJsonPath('data.session.draft_graph.nodes.0.position', ['x' => 10, 'y' => 20]);
});

it('409s a canvas sync that is behind the assistant', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();
    $session->addNode('from_assistant', 'transform', ['mapping' => []]);

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/draft", [
        'draft_lock_version' => 0,
        'nodes' => [],
        'edges' => [],
    ])->assertConflict();

    expect($session->fresh()->currentGraph()['nodes'])->toHaveCount(1);
});

it('422s a canvas sync with an invalid node config', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/draft", [
        'draft_lock_version' => 0,
        'nodes' => [['key' => 'a', 'type' => 'does_not_exist']],
        'edges' => [],
    ])->assertUnprocessable();
});

it('starts a session from a prompt and queues its first reply', function () {
    Queue::fake();
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions", [
        'prompt' => 'Every morning, email me yesterday\'s signups.',
    ])->assertCreated();

    expect($response->json('data.message.processing_status'))->toBe('pending');
    Queue::assertPushed(ProcessWorkflowBuilderMessageJob::class);
});

it('records where a session seeded from a workflow started, and names it after the workflow', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create(['name' => 'Lead Router']);
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ]);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions", [
        'workflow_id' => $workflow->id,
    ])->assertCreated();

    $session = WorkflowBuilderSession::find($response->json('data.session.id'));
    expect($session->title)->toBe('Lead Router');
    expect($session->draftVersions()->sole()->label)->toBe('Loaded from Lead Router');
});

it('refuses to promote an archived session', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create(['status' => BuilderSessionStatus::Archived]);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/promote")->assertConflict();
});
