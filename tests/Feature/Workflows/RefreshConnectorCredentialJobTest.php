<?php

use App\Jobs\Connectors\RefreshConnectorCredentialJob;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Notifications\Connectors\ConnectorCredentialExpiredNotification;
use App\Services\Connectors\OAuthConnectorFlowService;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

it('refreshes an oauth connector credential', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $connector = Connector::factory()->oauth()->create(['key' => 'github']);
    config(['services.github.client_id' => 'client-123', 'services.github.client_secret' => 'secret-123']);

    $credential = ConnectorCredential::factory()
        ->forWorkspace($workspace)
        ->forConnector($connector)
        ->create(['data' => ['access_token' => 'stale', 'refresh_token' => 'refresh-abc']]);

    Http::fake([
        $connector->oauth['token_url'] => Http::response(['access_token' => 'fresh', 'expires_in' => 3600]),
    ]);

    (new RefreshConnectorCredentialJob($credential))->handle(app(OAuthConnectorFlowService::class));

    expect($credential->fresh()->data['access_token'])->toBe('fresh');
    expect($credential->fresh()->data['refresh_token'])->toBe('refresh-abc');
});

it('marks the credential expired and notifies workspace admins on final refresh failure', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $connector = Connector::factory()->oauth()->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)->create();

    (new RefreshConnectorCredentialJob($credential))->failed(new RuntimeException('refresh failed'));

    expect($credential->fresh()->isExpired())->toBeTrue();
    Notification::assertSentTo($owner, ConnectorCredentialExpiredNotification::class);
});
