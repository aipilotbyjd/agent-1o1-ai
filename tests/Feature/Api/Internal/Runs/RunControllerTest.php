<?php

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('starts a run for a published workflow and it completes synchronously', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['x' => 'input.x']]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/runs", [
        'input' => ['x' => 42],
    ]);

    $response->assertStatus(202);
    expect($response->json('data.run.status'))->toBe('completed');
    expect($response->json('data.run.output'))->toBe(['a' => ['x' => 42]]);
});

it('rejects starting a run for an unpublished workflow', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/runs")
        ->assertStatus(422);
});

it('lists and shows runs scoped to the workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]], 'edges' => []]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/runs")->assertStatus(202);

    $index = $this->getJson("/api/v1/workspaces/{$workspace->id}/runs");
    $index->assertOk();
    expect($index->json('data'))->toHaveCount(1);

    $runId = $index->json('data.0.id');

    $this->getJson("/api/v1/workspaces/{$workspace->id}/runs/{$runId}")
        ->assertOk()
        ->assertJsonPath('data.run.id', $runId);
});

it('exposes total credits used and duration on a completed run', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'a', 'to' => 'b']],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/runs");
    $runId = $response->json('data.run.id');

    $show = $this->getJson("/api/v1/workspaces/{$workspace->id}/runs/{$runId}")->assertOk();

    // Two free `transform` nodes: 1 base credit each.
    expect($show->json('data.run.total_credits_used'))->toBe(2);
    expect($show->json('data.run.duration_ms'))->toBeGreaterThanOrEqual(0);
});
