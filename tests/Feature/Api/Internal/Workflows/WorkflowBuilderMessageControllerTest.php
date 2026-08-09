<?php

use App\Ai\Agents\WorkflowBuilderAgent;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('sends a chat message and persists both turns', function () {
    WorkflowBuilderAgent::fake(["Sure, I've added the node."]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/messages", [
        'message' => 'Add a node that sends a Slack message.',
    ]);

    $response->assertOk();
    expect($response->json('data.message.role'))->toBe('assistant');
    expect($response->json('data.message.content'))->toBe("Sure, I've added the node.");
    expect($session->fresh()->messages)->toHaveCount(2);
});

it('404s sending a message to a session in a different workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $otherOwner = User::factory()->create();
    $otherWorkspace = app(WorkspaceService::class)->create($otherOwner, ['name' => 'Other']);

    $foreign = WorkflowBuilderSession::factory()->forWorkspace($otherWorkspace, $otherOwner)->create();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$foreign->id}/messages", [
        'message' => 'hi',
    ])->assertNotFound();
});
