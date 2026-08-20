<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\RunStatus;
use App\Models\Secrets\Secret;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\DryRunner;
use App\Services\Workspaces\WorkspaceService;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForSecretRuns(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

/**
 * A one-node workflow whose single `run_code` operation copies a template
 * straight into its output — the shortest way to observe what the engine
 * substituted into a node's config, and what it then persisted.
 */
function runWorkflowEchoing(Workspace $workspace, User $owner, string $template)
{
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => 'run_code', 'config' => [
                'operations' => [['op' => 'set', 'output' => 'echoed', 'value' => $template]],
            ]],
        ],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);

    return app(StartWorkflowRunAction::class)->execute($workflow->fresh(), [])->fresh(['nodeRuns']);
}

it('resolves a non-secret variable into a node config at run time', function () {
    [$workspace, $owner] = ownerWorkspaceForSecretRuns();
    Secret::factory()->forWorkspace($workspace)->variable()->create([
        'key' => 'API_BASE_URL',
        'value' => 'https://api.example.com',
    ]);

    $run = runWorkflowEchoing($workspace, $owner, 'Calling {{ vars.API_BASE_URL }}/v1');

    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->nodeRuns->firstWhere('key', 'a')->output)->toBe(['echoed' => 'Calling https://api.example.com/v1']);
});

it('resolves the same store under both the secrets and vars prefixes', function () {
    [$workspace, $owner] = ownerWorkspaceForSecretRuns();
    Secret::factory()->forWorkspace($workspace)->variable()->create(['key' => 'REGION', 'value' => 'eu-west-1']);

    $viaSecrets = runWorkflowEchoing($workspace, $owner, '{{ secrets.REGION }}');
    $viaVars = runWorkflowEchoing($workspace, $owner, '{{ vars.REGION }}');

    expect($viaSecrets->nodeRuns->firstWhere('key', 'a')->output)->toBe(['echoed' => 'eu-west-1']);
    expect($viaVars->nodeRuns->firstWhere('key', 'a')->output)->toBe(['echoed' => 'eu-west-1']);
});

it('redacts a secret value out of the persisted node output', function () {
    [$workspace, $owner] = ownerWorkspaceForSecretRuns();
    Secret::factory()->forWorkspace($workspace)->create(['key' => 'API_TOKEN', 'value' => 'tok_live_abcdef123456']);

    $run = runWorkflowEchoing($workspace, $owner, 'Bearer {{ secrets.API_TOKEN }}');

    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->nodeRuns->firstWhere('key', 'a')->output)->toBe(['echoed' => 'Bearer [redacted]']);
});

it('marks a resolved secret as used', function () {
    [$workspace, $owner] = ownerWorkspaceForSecretRuns();
    $secret = Secret::factory()->forWorkspace($workspace)->create(['key' => 'API_TOKEN', 'last_used_at' => null]);

    runWorkflowEchoing($workspace, $owner, '{{ secrets.API_TOKEN }}');

    expect($secret->fresh()->last_used_at)->not->toBeNull();
});

it('does not resolve a secret belonging to another workspace', function () {
    [$workspace, $owner] = ownerWorkspaceForSecretRuns();
    Secret::factory()->variable()->create(['key' => 'FOREIGN_KEY', 'value' => 'not-yours']);

    $run = runWorkflowEchoing($workspace, $owner, '{{ secrets.FOREIGN_KEY }}');

    expect($run->nodeRuns->firstWhere('key', 'a')->output)->toBe(['echoed' => null]);
});

it('leaves secrets out of the context a node receives', function () {
    [$workspace, $owner] = ownerWorkspaceForSecretRuns();
    Secret::factory()->forWorkspace($workspace)->create(['key' => 'API_TOKEN', 'value' => 'tok_live_abcdef123456']);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    // `copy` reads a dot-path out of the raw run context rather than going
    // through `{{ }}` resolution — the store must not be reachable that way.
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => 'run_code', 'config' => [
                'operations' => [
                    ['op' => 'copy', 'output' => 'sneaked', 'path' => 'secrets.API_TOKEN'],
                    ['op' => 'set', 'output' => 'referenced', 'value' => '{{ secrets.API_TOKEN }}'],
                ],
            ]],
        ],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);

    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh(), [])->fresh(['nodeRuns']);

    expect($run->nodeRuns->firstWhere('key', 'a')->output)->toBe([
        'sneaked' => null,
        'referenced' => '[redacted]',
    ]);
});

it('does not warn about secret references during a dry run', function () {
    $graph = [
        'nodes' => [
            ['key' => 'a', 'type' => 'run_code', 'config' => [
                'operations' => [
                    ['op' => 'set', 'output' => 'token', 'value' => '{{ secrets.API_TOKEN }}'],
                    ['op' => 'set', 'output' => 'region', 'value' => '{{ vars.REGION }}'],
                ],
            ]],
        ],
        'edges' => [],
    ];

    $result = app(DryRunner::class)->run($graph);

    expect($result['warnings'])->toBe([]);
    expect($result['ok'])->toBeTrue();
});
