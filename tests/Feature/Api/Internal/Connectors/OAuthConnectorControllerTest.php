<?php

use App\Enums\Connectors\ConnectorAuthType;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Connectors\OAuthConnectorState;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForOAuth(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

it('initiates the oauth flow with a state-backed authorize url', function () {
    [$workspace, $owner] = ownerWorkspaceForOAuth();
    $connector = Connector::factory()->oauth()->create(['key' => 'github']);

    config(['services.github.client_id' => 'client-123']);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/oauth/initiate", [
        'connector_id' => $connector->id,
        'name' => 'My GitHub',
        'redirect_uri' => 'https://app.test/callback',
    ]);

    $response->assertOk();
    expect($response->json('data.authorize_url'))->toStartWith($connector->oauth['authorize_url']);
    expect($response->json('data.authorize_url'))->toContain('client_id=client-123');
    expect(OAuthConnectorState::where('state', $response->json('data.state'))->exists())->toBeTrue();
});

it('exchanges the callback code for tokens and stores a connector credential', function () {
    [$workspace, $owner] = ownerWorkspaceForOAuth();
    $connector = Connector::factory()->oauth()->create(['key' => 'github']);

    config(['services.github.client_id' => 'client-123', 'services.github.client_secret' => 'secret-123']);

    Http::fake([
        $connector->oauth['token_url'] => Http::response([
            'access_token' => 'gh-access-token',
            'refresh_token' => 'gh-refresh-token',
            'token_type' => 'bearer',
            'expires_in' => 3600,
        ]),
    ]);

    $state = OAuthConnectorState::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'connector_id' => $connector->id,
        'state' => 'test-state-token',
        'name' => 'My GitHub',
        'redirect_uri' => 'https://app.test/callback',
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->getJson('/api/oauth/connectors/callback?'.http_build_query([
        'state' => $state->state,
        'code' => 'auth-code-123',
    ]));

    $response->assertCreated();
    expect($response->json('data.connector_credential'))->not->toHaveKey('data');

    $credential = ConnectorCredential::query()->where('workspace_id', $workspace->id)->firstOrFail();
    expect($credential->name)->toBe('My GitHub');
    expect($credential->data['access_token'])->toBe('gh-access-token');
    expect($credential->data['refresh_token'])->toBe('gh-refresh-token');
    expect($credential->expires_at)->not->toBeNull();

    expect(OAuthConnectorState::where('state', 'test-state-token')->exists())->toBeFalse();
});

it('rejects an unknown or expired oauth state on callback', function () {
    $response = $this->getJson('/api/oauth/connectors/callback?'.http_build_query([
        'state' => 'does-not-exist',
        'code' => 'auth-code-123',
    ]));

    $response->assertStatus(422);
});

it('rejects storing a manual credential for an oauth-only connector at the schema level', function () {
    $connector = Connector::factory()->oauth()->create();

    expect($connector->auth_type)->toBe(ConnectorAuthType::OAuth2);
    expect($connector->isOAuth())->toBeTrue();
});
