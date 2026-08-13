<?php

use App\Enums\Connectors\ConnectorAuthType;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForConnectors(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

it('lists the connector catalog', function () {
    Connector::factory()->create(['key' => 'slack', 'name' => 'Slack']);
    [, $owner] = ownerWorkspaceForConnectors();

    Passport::actingAs($owner);

    $response = $this->getJson('/api/v1/connectors');

    $response->assertOk();
    expect(collect($response->json('data.connectors'))->pluck('key'))->toContain('slack');
});

it('creates a manual connector credential and never exposes its data', function () {
    [$workspace, $owner] = ownerWorkspaceForConnectors();
    $connector = Connector::factory()->create(['auth_type' => ConnectorAuthType::ApiKey]);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/connector-credentials", [
        'connector_id' => $connector->id,
        'name' => 'My API Key',
        'data' => ['api_key' => 'sk_test_secret'],
    ]);

    $response->assertCreated();
    expect($response->json('data.connector_credential.name'))->toBe('My API Key');
    expect($response->json('data.connector_credential'))->not->toHaveKey('data');
    $response->assertJsonMissing(['sk_test_secret']);

    $credential = ConnectorCredential::query()->firstWhere('name', 'My API Key');
    expect($credential->data)->toBe(['api_key' => 'sk_test_secret']);
});

it('rejects storing credential data directly for an oauth-only connector', function () {
    [$workspace, $owner] = ownerWorkspaceForConnectors();
    $connector = Connector::factory()->oauth()->create();

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/connector-credentials", [
        'connector_id' => $connector->id,
        'name' => 'Should fail',
        'data' => ['access_token' => 'abc'],
    ]);

    $response->assertStatus(422);
    expect(ConnectorCredential::query()->where('name', 'Should fail')->exists())->toBeFalse();
});

it('does not leak another workspace connector credential', function () {
    [$workspace, $owner] = ownerWorkspaceForConnectors();
    [$otherWorkspace] = ownerWorkspaceForConnectors();
    $foreign = ConnectorCredential::factory()->forWorkspace($otherWorkspace)->create();

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/{$foreign->id}")->assertNotFound();
    $this->patchJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/{$foreign->id}", ['name' => 'x'])->assertNotFound();
    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/{$foreign->id}")->assertNotFound();
});

it('updates and deletes a connector credential', function () {
    [$workspace, $owner] = ownerWorkspaceForConnectors();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)->create(['name' => 'Old name']);

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/{$credential->id}", ['name' => 'New name'])
        ->assertOk()
        ->assertJsonPath('data.connector_credential.name', 'New name');

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/{$credential->id}")->assertNoContent();
    expect(ConnectorCredential::find($credential->id))->toBeNull();
});
