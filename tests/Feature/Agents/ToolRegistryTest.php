<?php

use App\Ai\Tools\NodeTool;
use App\Ai\Tools\WorkflowTool;
use App\Models\Agents\Agent;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Agents\ToolRegistry;
use App\Services\Workspaces\WorkspaceService;

it('builds one NodeTool per attached node and one WorkflowTool per attached workflow', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $run = Run::factory()->create();

    $agent->toolBindings()->create([
        'node_type' => 'call_api',
        'config' => ['url' => 'https://internal.example.com', 'method' => 'GET'],
        'exposed_fields' => [],
    ]);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $agent->workflows()->attach($workflow->id);

    $tools = app(ToolRegistry::class)->toolsFor($agent, $run);

    expect($tools)->toHaveCount(2);
    expect($tools[0])->toBeInstanceOf(NodeTool::class);
    expect($tools[0]->name())->toBe('call_api');
    expect($tools[1])->toBeInstanceOf(WorkflowTool::class);
});

it('silently skips a binding whose node type is no longer registered', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $run = Run::factory()->create();

    $agent->toolBindings()->create(['node_type' => 'custom:999999', 'config' => [], 'exposed_fields' => []]);

    $tools = app(ToolRegistry::class)->toolsFor($agent, $run);

    expect($tools)->toBe([]);
});
