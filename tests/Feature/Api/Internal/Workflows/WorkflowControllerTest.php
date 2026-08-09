<?php

use App\Enums\Workspaces\Role;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForWorkflow(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

it('creates a draft workflow with a generated slug', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows", [
        'name' => 'Weekly Digest',
    ]);

    $response->assertCreated();
    expect($response->json('data.workflow.slug'))->toStartWith('weekly-digest-');
    expect($response->json('data.workflow.status'))->toBe('draft');
    expect($response->json('data.workflow.is_published'))->toBeFalse();
});

it('404s reading a workflow that belongs to a different workspace', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    [$otherWorkspace] = ownerWorkspaceForWorkflow();

    $foreign = Workflow::factory()->forWorkspace($otherWorkspace)->create();

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/workflows/{$foreign->id}")->assertNotFound();
});

it('saves a draft graph via the builder endpoint', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $response = $this->putJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph", [
        'nodes' => [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [
            ['from' => 'a', 'to' => 'b'],
        ],
    ]);

    $response->assertOk();
    expect($response->json('data.workflow.nodes'))->toHaveCount(2);
    expect($response->json('data.workflow.has_unpublished_changes'))->toBeTrue();
});

it('rejects a draft graph with a schema-invalid node config', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $response = $this->putJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph", [
        'nodes' => [
            ['key' => 'a', 'type' => 'call_api', 'config' => ['url' => 'https://example.com']],
        ],
        'edges' => [],
    ]);

    $response->assertStatus(422);
    expect($response->json('errors'))->toContain("Node 'a': config.method is required.");
});

it('publishes a valid draft as a new version', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph", [
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ])->assertOk();

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/versions", [
        'notes' => 'first cut',
    ]);

    $response->assertCreated();
    expect($response->json('data.version.version'))->toBe(1);
    expect($response->json('data.workflow.is_published'))->toBeTrue();
    expect($response->json('data.workflow.has_unpublished_changes'))->toBeFalse();
});

it('rejects publishing a graph that fails GraphValidator', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph", [
        'nodes' => [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [
            ['from' => 'a', 'to' => 'b'],
            ['from' => 'b', 'to' => 'a'],
        ],
    ])->assertOk();

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/versions");

    $response->assertStatus(422);
    expect($response->json('errors'))->toBe(['The graph contains a cycle.']);
});

it('lets a viewer read workflows but not manage or publish them', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($viewer);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/workflows")->assertOk();
    $this->patchJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}", ['name' => 'x'])->assertForbidden();
    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/versions")->assertForbidden();
});
