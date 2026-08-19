<?php

use App\Enums\Triggers\TriggerType;
use App\Enums\Workspaces\Role;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspace(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

it('creates a webhook trigger, issuing a token', function () {
    [$workspace, $owner] = ownerWorkspace();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/triggers", [
        'target_type' => 'workflow',
        'target_id' => $workflow->id,
        'type' => 'webhook',
    ]);

    $response->assertCreated();
    expect($response->json('data.trigger.token'))->not->toBeNull();
});

it('does not issue a token for a manual trigger', function () {
    [$workspace, $owner] = ownerWorkspace();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/triggers", [
        'target_type' => 'workflow',
        'target_id' => $workflow->id,
        'type' => 'manual',
    ]);

    $response->assertCreated();
    expect($response->json('data.trigger.token'))->toBeNull();
});

it('lets a viewer read triggers but not manage them', function () {
    [$workspace, $owner] = ownerWorkspace();
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);

    $trigger = Trigger::factory()->webhook()->forWorkspace($workspace)->create();

    Passport::actingAs($viewer);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/triggers")->assertOk();

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/triggers/{$trigger->id}", ['is_active' => false])
        ->assertForbidden();
});

it('404s updating a trigger that belongs to a different workspace', function () {
    [$workspace, $owner] = ownerWorkspace();
    [$otherWorkspace] = ownerWorkspace();

    $foreignTrigger = Trigger::factory()->webhook()->forWorkspace($otherWorkspace)->create();

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/triggers/{$foreignTrigger->id}", ['is_active' => false])
        ->assertNotFound();
});

it('rotates a webhook token, invalidating the old one', function () {
    [$workspace, $owner] = ownerWorkspace();
    $trigger = Trigger::factory()->webhook()->forWorkspace($workspace)->create();
    $oldToken = $trigger->token;

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/triggers/{$trigger->id}/rotate-token");

    $response->assertOk();
    $newToken = $response->json('data.trigger.token');

    expect($newToken)->not->toBe($oldToken);
    $this->postJson("/api/hooks/{$oldToken}", [])->assertNotFound();
});

it('rejects rotating a token on a non-webhook trigger', function () {
    [$workspace, $owner] = ownerWorkspace();
    $trigger = Trigger::factory()->forWorkspace($workspace)->create(['type' => TriggerType::Manual]);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/triggers/{$trigger->id}/rotate-token")
        ->assertStatus(422);
});

it('queues a run from the manual endpoint', function () {
    [$workspace, $owner] = ownerWorkspace();
    $trigger = Trigger::factory()->forWorkspace($workspace)->create(['type' => TriggerType::Manual]);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/triggers/{$trigger->id}/run")
        ->assertStatus(202);

    expect($trigger->events()->count())->toBe(1);
});

it('lists a trigger\'s events newest first, paginated', function () {
    [$workspace, $owner] = ownerWorkspace();
    $trigger = Trigger::factory()->webhook()->forWorkspace($workspace)->create();

    TriggerEvent::factory()->for($trigger, 'trigger')->count(3)->create();

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/triggers/{$trigger->id}/events");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
    expect($response->json('meta.total'))->toBe(3);
});

it('rejects a trigger pointed at another workspace\'s workflow', function () {
    [$workspace, $owner] = ownerWorkspace();
    [$otherWorkspace] = ownerWorkspace();
    $foreignWorkflow = Workflow::factory()->forWorkspace($otherWorkspace)->create();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/triggers", [
        'target_type' => 'workflow',
        'target_id' => $foreignWorkflow->id,
        'type' => 'webhook',
    ])->assertJsonValidationErrors('target_id');
});
