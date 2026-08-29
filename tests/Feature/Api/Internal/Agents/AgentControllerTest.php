<?php

use App\Ai\Agents\WorkspaceAgent;
use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Ai\ModelCatalog;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('creates an agent with a generated slug', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/agents", [
        'name' => 'Support Bot',
        'instructions' => 'You help customers.',
    ]);

    $response->assertCreated();
    expect($response->json('data.agent.slug'))->toStartWith('support-bot-');
    expect($response->json('data.agent.provider'))->toBe('anthropic');
});

it('opts an agent into a model catalog entry via update, and back out again', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $catalog = ModelCatalog::factory()->create(['slug' => 'claude-3-5-sonnet']);

    Passport::actingAs($owner);

    $response = $this->patchJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}", [
        'model_catalog_id' => $catalog->id,
    ]);

    $response->assertOk();
    expect($response->json('data.agent.model_catalog_id'))->toBe($catalog->id);
    expect($agent->fresh()->model_catalog_id)->toBe($catalog->id);

    $response = $this->patchJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}", [
        'model_catalog_id' => null,
    ]);

    $response->assertOk();
    expect($response->json('data.agent.model_catalog_id'))->toBeNull();
});

it('rejects an unknown model catalog id', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}", [
        'model_catalog_id' => 999999,
    ])->assertJsonValidationErrors('model_catalog_id');
});

it('404s reading an agent that belongs to a different workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $otherWorkspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);

    $foreign = Agent::factory()->forWorkspace($otherWorkspace)->create();

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$foreign->id}")->assertNotFound();
});

it('creates a session and sends a message through the internal api', function () {
    WorkspaceAgent::fake(['Hi, how can I help?']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $sessionResponse = $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/sessions");
    $sessionResponse->assertCreated();
    $sessionId = $sessionResponse->json('data.session.id');

    $messageResponse = $this->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/sessions/{$sessionId}/messages",
        ['message' => 'Hello'],
    );

    $messageResponse->assertOk();
    expect($messageResponse->json('data.message.content'))->toBe('Hi, how can I help?');
    expect($messageResponse->json('data.message.role'))->toBe('assistant');
});

it('lets a member chat but not manage agents', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $member = User::factory()->create();
    $workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);

    $agent = Agent::factory()->forWorkspace($workspace)->create();

    WorkspaceAgent::fake(['ok']);
    Passport::actingAs($member);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/sessions")->assertCreated();
    $this->patchJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}", ['name' => 'x'])->assertForbidden();
});
