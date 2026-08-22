<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Exceptions\WorkflowValidationException;
use App\Models\Connectors\Connector;
use App\Models\Nodes\CustomNode;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;

/**
 * A `CustomNode` carrying an `implementation` runs for real — the other half
 * of `CustomNodeHandlingTest`, which covers definition-only rows.
 */
function executableCustomNodeWorkspace(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$owner, $workspace];
}

function runGraphWithCustomNode(Workspace $workspace, User $owner, CustomNode $custom, array $config = []): array
{
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => $custom->nodeType(), 'config' => $config]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);

    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh())->fresh(['nodeRuns']);

    return [$run, $run->nodeRuns->firstWhere('key', 'a')];
}

it('executes a custom node as the HTTP request its implementation describes', function () {
    [$owner, $workspace] = executableCustomNodeWorkspace();

    Http::fake([
        'api.example.test/*' => Http::response(['id' => 42, 'ok' => true], 201),
    ]);

    $custom = CustomNode::factory()->forWorkspace($workspace)->executable([
        'method' => 'POST',
        'url' => 'https://api.example.test/widgets/{{ config.widget_id }}',
        'headers' => ['X-Trace' => 'run-{{ config.widget_id }}'],
        'body' => ['label' => '{{ config.label }}'],
    ])->create([
        'config_schema' => [
            'type' => 'object',
            'required' => ['widget_id', 'label'],
            'properties' => [
                'widget_id' => ['type' => 'string'],
                'label' => ['type' => 'string'],
            ],
        ],
    ]);

    [$run, $nodeRun] = runGraphWithCustomNode($workspace, $owner, $custom, [
        'widget_id' => 'w-1',
        'label' => 'Hello',
    ]);

    expect($run->status)->toBe(RunStatus::Completed);
    expect($nodeRun->status)->toBe(NodeRunStatus::Completed);
    expect($nodeRun->output['status'])->toBe(201);
    expect($nodeRun->output['body'])->toBe(['id' => 42, 'ok' => true]);

    // The caller's `config` lands only in the positions the *author* chose.
    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/widgets/w-1'
            && $request->header('X-Trace') === ['run-w-1']
            && $request->data() === ['label' => 'Hello'];
    });
});

it('appends the implementation query string to the resolved url', function () {
    [$owner, $workspace] = executableCustomNodeWorkspace();

    Http::fake(['api.example.test/*' => Http::response([], 200)]);

    $custom = CustomNode::factory()->forWorkspace($workspace)->executable([
        'method' => 'GET',
        'url' => 'https://api.example.test/search',
        'query' => ['q' => '{{ config.term }}', 'limit' => 10],
    ])->create([
        'config_schema' => ['type' => 'object', 'properties' => ['term' => ['type' => 'string']]],
    ]);

    runGraphWithCustomNode($workspace, $owner, $custom, ['term' => 'boxes']);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.example.test/search?q=boxes&limit=10');
});

it('injects the workspace credential without leaking it into the run log', function () {
    [$owner, $workspace] = executableCustomNodeWorkspace();

    Http::fake([
        // An endpoint that echoes the token it was called with — the exact
        // shape that would otherwise write a plaintext secret into node_runs.
        'api.example.test/*' => Http::response(['echoed' => 'Bearer s3cret-token'], 200),
    ]);

    $connector = Connector::factory()->create(['key' => 'acme']);
    $credential = $workspace->connectorCredentials()->create([
        'connector_id' => $connector->id,
        'name' => 'Acme key',
        'data' => ['access_token' => 's3cret-token'],
    ]);

    $custom = CustomNode::factory()->forWorkspace($workspace)->executable([
        'method' => 'GET',
        'url' => 'https://api.example.test/me',
        'headers' => ['Authorization' => 'Bearer {{ credential.access_token }}'],
    ])->create([
        'credential_type' => 'acme',
        'config_schema' => ['type' => 'object', 'properties' => ['credential_id' => ['type' => 'integer']]],
    ]);

    [, $nodeRun] = runGraphWithCustomNode($workspace, $owner, $custom, [
        'credential_id' => $credential->id,
    ]);

    expect($nodeRun->status)->toBe(NodeRunStatus::Completed);

    Http::assertSent(fn ($request) => $request->header('Authorization') === ['Bearer s3cret-token']);

    expect(json_encode($nodeRun->output))->not->toContain('s3cret-token');
});

it('fails the node when a deactivated custom node is reached', function () {
    [$owner, $workspace] = executableCustomNodeWorkspace();

    Http::fake();

    $custom = CustomNode::factory()->forWorkspace($workspace)->executable()->create();

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => $custom->nodeType(), 'config' => []]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);

    // Deactivated after publishing — the graph still references it.
    $custom->update(['is_active' => false]);

    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh())->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Failed);
    expect($run->nodeRuns->firstWhere('key', 'a')->error)->toContain('not available in this workspace');

    Http::assertNothingSent();
});

it('validates a placed custom node against its author-supplied config schema', function () {
    [, $workspace] = executableCustomNodeWorkspace();

    $custom = CustomNode::factory()->forWorkspace($workspace)->executable()->create([
        'config_schema' => [
            'type' => 'object',
            'required' => ['widget_id'],
            'properties' => ['widget_id' => ['type' => 'string']],
        ],
    ]);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    expect(fn () => $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => $custom->nodeType(), 'config' => []]],
        'edges' => [],
    ]))->toThrow(WorkflowValidationException::class);
});
