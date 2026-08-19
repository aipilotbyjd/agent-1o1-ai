<?php

use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentKnowledge;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\SkillInjector;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User, 2: Agent}
 */
function agentWorkspaceForKnowledge(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner, Agent::factory()->forWorkspace($workspace)->create()];
}

it('creates a knowledge entry and estimates its token cost', function () {
    [$workspace, $owner, $agent] = agentWorkspaceForKnowledge();
    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge", [
        'title' => 'Refund policy',
        'content' => str_repeat('a', 400),
        'sort_order' => 2,
    ]);

    $response->assertCreated();
    expect($response->json('data.knowledge.title'))->toBe('Refund policy');
    expect($response->json('data.knowledge.tokens'))->toBe(100);
    expect($response->json('data.knowledge.is_active'))->toBeTrue();
    expect($response->json('data.knowledge.source_type'))->toBe('text');
});

it('lists entries in sort order', function () {
    [$workspace, $owner, $agent] = agentWorkspaceForKnowledge();
    Passport::actingAs($owner);

    $agent->knowledge()->create(['title' => 'second', 'content' => 'b', 'sort_order' => 5]);
    $agent->knowledge()->create(['title' => 'first', 'content' => 'a', 'sort_order' => 1]);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge");

    $response->assertOk();
    expect($response->json('data.knowledge.0.title'))->toBe('first');
    expect($response->json('data.knowledge.1.title'))->toBe('second');
});

it('re-estimates tokens when content is updated', function () {
    [$workspace, $owner, $agent] = agentWorkspaceForKnowledge();
    Passport::actingAs($owner);

    $entry = $agent->knowledge()->create(['title' => 'Policy', 'content' => 'short', 'tokens' => 2]);

    $response = $this->patchJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge/{$entry->id}",
        ['content' => str_repeat('b', 200)],
    );

    $response->assertOk();
    expect($response->json('data.knowledge.tokens'))->toBe(50);
});

it('injects only active entries into the agent prompt', function () {
    [, , $agent] = agentWorkspaceForKnowledge();

    $agent->knowledge()->create(['title' => 'Live', 'content' => 'live content', 'is_active' => true]);
    $agent->knowledge()->create(['title' => 'Retired', 'content' => 'retired content', 'is_active' => false]);

    $instructions = app(SkillInjector::class)->instructionsFor($agent);

    expect($instructions)->toContain('## Knowledge: Live');
    expect($instructions)->not->toContain('retired content');
});

it('deletes an entry', function () {
    [$workspace, $owner, $agent] = agentWorkspaceForKnowledge();
    Passport::actingAs($owner);

    $entry = $agent->knowledge()->create(['title' => 'Policy', 'content' => 'a']);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge/{$entry->id}")
        ->assertNoContent();

    expect(AgentKnowledge::count())->toBe(0);
});

it('404s an entry belonging to a different agent', function () {
    [$workspace, $owner, $agent] = agentWorkspaceForKnowledge();
    $otherAgent = Agent::factory()->forWorkspace($workspace)->create();
    Passport::actingAs($owner);

    $foreignEntry = $otherAgent->knowledge()->create(['title' => 'Theirs', 'content' => 'a']);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge/{$foreignEntry->id}")
        ->assertNotFound();
});

it('requires a url when the source type is url', function () {
    [$workspace, $owner, $agent] = agentWorkspaceForKnowledge();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge", [
        'title' => 'Docs',
        'content' => 'a',
        'source_type' => 'url',
    ])->assertJsonValidationErrors('source_url');
});

it('lets a viewer read knowledge but not write it', function () {
    [$workspace, , $agent] = agentWorkspaceForKnowledge();
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);
    Passport::actingAs($viewer);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge")->assertOk();

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge", [
        'title' => 'Nope',
        'content' => 'a',
    ])->assertForbidden();
});
