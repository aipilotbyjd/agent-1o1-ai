<?php

use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
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
    expect($session->fresh()->status)->toBe('promoted');
});

it('deletes a session', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    Passport::actingAs($owner);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}")->assertNoContent();
    expect(WorkflowBuilderSession::find($session->id))->toBeNull();
});
