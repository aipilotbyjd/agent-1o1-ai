<?php

use App\Models\Agents\Agent;
use App\Models\User;
use App\Models\Workflows\Tag;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForTag(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

it('creates a tag', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/tags", [
        'name' => 'urgent',
        'color' => '#ff0000',
    ]);

    $response->assertCreated();
    expect($response->json('data.tag.name'))->toBe('urgent');
});

it('rejects a duplicate tag name in the same workspace', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    Tag::factory()->forWorkspace($workspace)->create(['name' => 'urgent']);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/tags", ['name' => 'urgent'])
        ->assertStatus(422);
});

it('lists tags with workflow counts', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    $tag = Tag::factory()->forWorkspace($workspace)->create();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $tag->workflows()->attach($workflow);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/tags");

    $response->assertOk();
    expect($response->json('data.tags.0.workflow_count'))->toBe(1);
});

it('syncs tags onto a workflow', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    $tags = Tag::factory()->forWorkspace($workspace)->count(2)->create();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $response = $this->putJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/tags", [
        'tag_ids' => $tags->pluck('id')->all(),
    ]);

    $response->assertOk();
    expect($workflow->fresh()->tags)->toHaveCount(2);
});

it('syncs tags onto an agent', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    $tags = Tag::factory()->forWorkspace($workspace)->count(2)->create();
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $response = $this->putJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/tags", [
        'tag_ids' => $tags->pluck('id')->all(),
    ]);

    $response->assertOk();
    expect($agent->fresh()->tags)->toHaveCount(2);
});

it('shares a tag across a workflow and an agent, counted separately', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    $tag = Tag::factory()->forWorkspace($workspace)->create();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $tag->workflows()->attach($workflow);
    $tag->agents()->attach($agent);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/tags");

    $response->assertOk();
    expect($response->json('data.tags.0.workflow_count'))->toBe(1);
    expect($response->json('data.tags.0.agent_count'))->toBe(1);
});

it('deletes a tag', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    $tag = Tag::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/tags/{$tag->id}")->assertNoContent();
    expect(Tag::find($tag->id))->toBeNull();
});

it('404s deleting a tag that belongs to a different workspace', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    [$otherWorkspace] = ownerWorkspaceForTag();
    $foreign = Tag::factory()->forWorkspace($otherWorkspace)->create();

    Passport::actingAs($owner);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/tags/{$foreign->id}")->assertNotFound();
});
