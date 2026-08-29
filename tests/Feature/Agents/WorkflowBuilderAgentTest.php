<?php

use App\Actions\Workflows\Builder\SendWorkflowBuilderMessageAction;
use App\Ai\Agents\WorkflowBuilderAgent;
use App\Ai\Tools\WorkflowBuilder\AddNodeTool;
use App\Ai\Tools\WorkflowBuilder\ConnectNodesTool;
use App\Ai\Tools\WorkflowBuilder\ValidateWorkflowTool;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Tools\Request as ToolRequest;

it('exposes the nine builder tools bound to the session', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    $agent = new WorkflowBuilderAgent($session);

    expect(collect(iterator_to_array($agent->tools()))->map->name()->all())->toBe([
        'list_available_nodes', 'inspect_node_schema', 'add_node', 'update_node',
        'remove_node', 'connect_nodes', 'disconnect_nodes', 'validate_workflow', 'dry_run_workflow',
    ]);
});

it('runs an add_node then connect_nodes tool sequence against the draft', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    $agent = new WorkflowBuilderAgent($session);
    $tools = collect(iterator_to_array($agent->tools()))->keyBy(fn ($tool) => $tool->name());

    expect($tools['add_node'])->toBeInstanceOf(AddNodeTool::class);

    $tools['add_node']->handle(new ToolRequest([
        'key' => 'a', 'type' => 'run_code',
        'config_json' => json_encode(['operations' => [['op' => 'set', 'output' => 'result', 'value' => '1']]]),
    ], 'call-1'));

    $tools['add_node']->handle(new ToolRequest([
        'key' => 'b', 'type' => 'run_code',
        'config_json' => json_encode(['operations' => [['op' => 'set', 'output' => 'result', 'value' => '2']]]),
    ], 'call-2'));

    expect($tools['connect_nodes'])->toBeInstanceOf(ConnectNodesTool::class);
    $tools['connect_nodes']->handle(new ToolRequest(['from' => 'a', 'to' => 'b'], 'call-3'));

    expect($tools['validate_workflow'])->toBeInstanceOf(ValidateWorkflowTool::class);
    $validation = $tools['validate_workflow']->handle(new ToolRequest([], 'call-4'));
    expect((string) $validation)->toContain('valid');

    $session->refresh();
    expect($session->currentGraph()['nodes'])->toHaveCount(2);
    expect($session->currentGraph()['edges'])->toHaveCount(1);
    expect($session->draftVersions)->toHaveCount(3);
});

it('sends a chat message, persists both turns, and excludes the live user message from history', function () {
    WorkflowBuilderAgent::fake(['reply one', 'reply two']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    $reply = app(SendWorkflowBuilderMessageAction::class)->execute($session, 'first message');

    expect($reply->role)->toBe('assistant');
    expect($reply->content)->toBe('reply one');
    expect($session->fresh()->messages)->toHaveCount(2);

    app(SendWorkflowBuilderMessageAction::class)->execute($session, 'second message');

    expect($session->fresh()->messages)->toHaveCount(4);

    WorkflowBuilderAgent::assertPrompted(function ($prompt) {
        return $prompt->prompt === 'second message';
    });
});

it('falls back to the default provider when the workflow-builder-assistant catalog entry has no route', function () {
    WorkflowBuilderAgent::fake(['reply']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    app(SendWorkflowBuilderMessageAction::class)->execute($session, 'hello');

    WorkflowBuilderAgent::assertPrompted(function (AgentPrompt $prompt) {
        return $prompt->provider->name() === config('ai.default');
    });
});

it('resolves the workflow-builder-assistant catalog chain when it has an enabled route', function () {
    WorkflowBuilderAgent::fake(['reply']);

    $catalog = ModelCatalog::factory()->create(['slug' => 'workflow-builder-assistant', 'is_internal' => true]);
    ModelRoute::factory()->forCatalog($catalog)->create([
        'execution_provider' => 'fireworks',
        'execution_model_id' => 'accounts/fireworks/models/llama-v3p1-70b-instruct',
        'priority' => 0,
    ]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    app(SendWorkflowBuilderMessageAction::class)->execute($session, 'hello');

    WorkflowBuilderAgent::assertPrompted(function (AgentPrompt $prompt) {
        return $prompt->provider->name() === 'fireworks'
            && $prompt->model === 'accounts/fireworks/models/llama-v3p1-70b-instruct';
    });
});
