<?php

use App\Models\Agents\Agent;
use App\Models\Artifacts\Artifact;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForArtifacts(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

it('lists only the latest version per artifact group', function () {
    Storage::fake('local');
    [$workspace, $owner] = ownerWorkspaceForArtifacts();
    Passport::actingAs($owner);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $groupId = (string) Str::uuid();
    Artifact::create([
        'workspace_id' => $workspace->id,
        'agent_id' => $agent->id,
        'created_by' => $owner->id,
        'group_id' => $groupId,
        'version' => 1,
        'filename' => 'report.pdf',
        'mime_type' => 'application/pdf',
        'size' => 100,
        'disk' => 'local',
        'path' => 'artifacts/report-v1.pdf',
    ]);
    Artifact::create([
        'workspace_id' => $workspace->id,
        'agent_id' => $agent->id,
        'created_by' => $owner->id,
        'group_id' => $groupId,
        'version' => 2,
        'filename' => 'report.pdf',
        'mime_type' => 'application/pdf',
        'size' => 150,
        'disk' => 'local',
        'path' => 'artifacts/report-v2.pdf',
    ]);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/artifacts");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.version'))->toBe(2);
    expect($response->json('data.0.versions_count'))->toBe(2);
});

it('filters artifacts by mime category', function () {
    Storage::fake('local');
    [$workspace, $owner] = ownerWorkspaceForArtifacts();
    Passport::actingAs($owner);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    Artifact::create([
        'workspace_id' => $workspace->id,
        'agent_id' => $agent->id,
        'group_id' => (string) Str::uuid(),
        'version' => 1,
        'filename' => 'chart.png',
        'mime_type' => 'image/png',
        'size' => 10,
        'disk' => 'local',
        'path' => 'artifacts/chart.png',
    ]);
    Artifact::create([
        'workspace_id' => $workspace->id,
        'agent_id' => $agent->id,
        'group_id' => (string) Str::uuid(),
        'version' => 1,
        'filename' => 'data.csv',
        'mime_type' => 'text/csv',
        'size' => 10,
        'disk' => 'local',
        'path' => 'artifacts/data.csv',
    ]);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/artifacts?mime_category=images");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.filename'))->toBe('chart.png');
});

it('downloads an artifact file', function () {
    Storage::fake('local');
    [$workspace, $owner] = ownerWorkspaceForArtifacts();
    Passport::actingAs($owner);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    Storage::disk('local')->put('artifacts/report.pdf', 'contents');
    $artifact = Artifact::create([
        'workspace_id' => $workspace->id,
        'agent_id' => $agent->id,
        'group_id' => (string) Str::uuid(),
        'version' => 1,
        'filename' => 'report.pdf',
        'mime_type' => 'application/pdf',
        'size' => 8,
        'disk' => 'local',
        'path' => 'artifacts/report.pdf',
    ]);

    $response = $this->get("/api/v1/workspaces/{$workspace->id}/artifacts/{$artifact->id}/download");

    $response->assertOk();
});

it('deletes every version in the group', function () {
    Storage::fake('local');
    [$workspace, $owner] = ownerWorkspaceForArtifacts();
    Passport::actingAs($owner);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $groupId = (string) Str::uuid();
    Storage::disk('local')->put('artifacts/v1.pdf', 'a');
    Storage::disk('local')->put('artifacts/v2.pdf', 'b');
    $v1 = Artifact::create([
        'workspace_id' => $workspace->id, 'agent_id' => $agent->id, 'group_id' => $groupId,
        'version' => 1, 'filename' => 'report.pdf', 'mime_type' => 'application/pdf',
        'size' => 1, 'disk' => 'local', 'path' => 'artifacts/v1.pdf',
    ]);
    Artifact::create([
        'workspace_id' => $workspace->id, 'agent_id' => $agent->id, 'group_id' => $groupId,
        'version' => 2, 'filename' => 'report.pdf', 'mime_type' => 'application/pdf',
        'size' => 1, 'disk' => 'local', 'path' => 'artifacts/v2.pdf',
    ]);

    $response = $this->deleteJson("/api/v1/workspaces/{$workspace->id}/artifacts/{$v1->id}");

    $response->assertNoContent();
    expect(Artifact::where('group_id', $groupId)->count())->toBe(0);
    Storage::disk('local')->assertMissing('artifacts/v1.pdf');
    Storage::disk('local')->assertMissing('artifacts/v2.pdf');
});

it('denies access to a viewer from another workspace', function () {
    Storage::fake('local');
    [$workspace, $owner] = ownerWorkspaceForArtifacts();
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $artifact = Artifact::create([
        'workspace_id' => $workspace->id, 'agent_id' => $agent->id, 'group_id' => (string) Str::uuid(),
        'version' => 1, 'filename' => 'report.pdf', 'mime_type' => 'application/pdf',
        'size' => 1, 'disk' => 'local', 'path' => 'artifacts/v1.pdf',
    ]);

    $stranger = User::factory()->create();
    $strangerWorkspace = app(WorkspaceService::class)->create($stranger, ['name' => 'Other']);
    Passport::actingAs($stranger);

    $response = $this->getJson("/api/v1/workspaces/{$strangerWorkspace->id}/artifacts/{$artifact->id}");

    $response->assertNotFound();
});
