<?php

use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Agents\DocumentEmbedding;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User, 2: Agent}
 */
function agentWorkspaceForKnowledgeSources(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner, Agent::factory()->forWorkspace($workspace)->create()];
}

it('lists attached and available collections', function () {
    [$workspace, $owner, $agent] = agentWorkspaceForKnowledgeSources();
    Passport::actingAs($owner);

    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'support', 'chunk_text' => 'a', 'embedding' => [1.0]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'sales', 'chunk_text' => 'b', 'embedding' => [1.0]]);
    $agent->knowledgeCollections()->create(['collection' => 'support']);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge-sources");

    $response->assertOk();
    expect($response->json('data.attached'))->toBe(['support']);
    expect($response->json('data.available'))->toBe(['sales', 'support']);
});

it('attaches and detaches a collection', function () {
    [$workspace, $owner, $agent] = agentWorkspaceForKnowledgeSources();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge-sources/support")
        ->assertOk()
        ->assertJsonPath('data.attached', ['support']);

    expect($agent->knowledgeCollections()->pluck('collection')->all())->toBe(['support']);

    // Attaching the same collection twice is a no-op, not a duplicate row.
    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge-sources/support")->assertOk();
    expect($agent->knowledgeCollections()->count())->toBe(1);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge-sources/support")
        ->assertNoContent();
    expect($agent->knowledgeCollections()->count())->toBe(0);
});

it('does not let a viewer attach a knowledge source', function () {
    [$workspace, , $agent] = agentWorkspaceForKnowledgeSources();
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);
    Passport::actingAs($viewer);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge-sources/support")
        ->assertForbidden();
});

it('404s for an agent from another workspace', function () {
    [$workspace, $owner, $agent] = agentWorkspaceForKnowledgeSources();
    // Same owner, a second workspace they're actually a member of — so the
    // 404 comes from the workspace mismatch, not from lacking permission.
    $otherWorkspace = app(WorkspaceService::class)->create($owner, ['name' => 'Other']);
    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$otherWorkspace->id}/agents/{$agent->id}/knowledge-sources")
        ->assertNotFound();
});
