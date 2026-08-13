<?php

use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\User;
use App\Nodes\Integrations\GitHub\GitHubGetRepoNode;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;

it('resolves an access token from a workspace-scoped connector credential', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $connector = Connector::factory()->create(['key' => 'github']);
    $credential = ConnectorCredential::factory()
        ->forWorkspace($workspace)
        ->forConnector($connector)
        ->create(['data' => ['access_token' => 'gh-from-credential']]);

    Http::fake(['api.github.com/repos/acme/widgets' => Http::response(['id' => 1])]);

    $run = Run::factory()->create(['workspace_id' => $workspace->id]);
    $node = new GitHubGetRepoNode;

    $node->execute($run, ['credential_id' => $credential->id, 'repo' => 'acme/widgets'], []);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer gh-from-credential'));
    expect($credential->fresh()->last_used_at)->not->toBeNull();
});

it('refuses a connector credential belonging to another workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $otherOwner = User::factory()->create();
    $otherWorkspace = app(WorkspaceService::class)->create($otherOwner, ['name' => 'Other']);
    $foreignCredential = ConnectorCredential::factory()->forWorkspace($otherWorkspace)->create();

    $run = Run::factory()->create(['workspace_id' => $workspace->id]);
    $node = new GitHubGetRepoNode;

    expect(fn () => $node->execute($run, ['credential_id' => $foreignCredential->id, 'repo' => 'acme/widgets'], []))
        ->toThrow(RuntimeException::class, 'not found in this workspace');
});

it('refuses an expired connector credential', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)->expired()->create();

    $run = Run::factory()->create(['workspace_id' => $workspace->id]);
    $node = new GitHubGetRepoNode;

    expect(fn () => $node->execute($run, ['credential_id' => $credential->id, 'repo' => 'acme/widgets'], []))
        ->toThrow(RuntimeException::class, 'has expired');
});
