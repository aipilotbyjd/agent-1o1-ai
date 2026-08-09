<?php

use App\Models\Agents\Agent;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('creates a skill with a generated slug', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/skills", [
        'name' => 'Refund Policy',
        'instructions' => 'Offer store credit first.',
    ]);

    $response->assertCreated();
    expect($response->json('data.skill.slug'))->toStartWith('refund-policy-');
    expect($response->json('data.skill.version'))->toBe(1);
});

it('bumps the version when instructions change but not for cosmetic edits', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}", ['color' => '#fff'])
        ->assertOk();
    expect($skill->fresh()->version)->toBe(1);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}", ['instructions' => 'v2'])
        ->assertOk();
    expect($skill->fresh()->version)->toBe(2);
});

it('attaches and detaches a skill to an agent', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/skills/{$skill->id}")
        ->assertOk();
    expect($agent->skills()->count())->toBe(1);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/skills/{$skill->id}")
        ->assertNoContent();
    expect($agent->skills()->count())->toBe(0);
});

it('404s attaching a skill from a different workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $otherWorkspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $foreignSkill = $otherWorkspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/skills/{$foreignSkill->id}")
        ->assertNotFound();
});
