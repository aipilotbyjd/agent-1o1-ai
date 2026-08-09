<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;

it('runs one child workflow per item and joins the results', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $child = Workflow::factory()->forWorkspace($workspace)->create();
    $child->replaceGraph(['nodes' => [
        ['key' => 'c', 'type' => 'transform', 'config' => ['mapping' => ['n' => 'input.item']]],
    ], 'edges' => []]);
    $child->publishVersion(publisher: $owner);
    $child = $child->fresh();

    $parent = Workflow::factory()->forWorkspace($workspace)->create();
    $parent->replaceGraph([
        'nodes' => [
            ['key' => 'loop', 'type' => 'loop', 'config' => [
                'items_path' => 'input.items',
                'workflow_id' => $child->id,
                'max_concurrent' => 1,
            ]],
            ['key' => 'after', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'loop', 'to' => 'after']],
    ]);
    $parent->publishVersion(publisher: $owner);
    $parent = $parent->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($parent, ['items' => [10, 20, 30]]);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);

    $loopNode = $run->nodeRuns->firstWhere('key', 'loop');
    expect($loopNode->status)->toBe(NodeRunStatus::Completed);
    expect($loopNode->output['results'])->toBe([
        0 => ['c' => ['n' => 10]],
        1 => ['c' => ['n' => 20]],
        2 => ['c' => ['n' => 30]],
    ]);
    expect($loopNode->output['errors'])->toBe([]);

    expect(Run::where('parent_node_id', $loopNode->id)->count())->toBe(3);
    expect($run->nodeRuns->firstWhere('key', 'after')->status)->toBe(NodeRunStatus::Completed);
});

it('completes with an empty result set when the items list is empty', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $child = Workflow::factory()->forWorkspace($workspace)->create();
    $child->replaceGraph(['nodes' => [['key' => 'c', 'type' => 'transform', 'config' => ['mapping' => []]]], 'edges' => []]);
    $child->publishVersion(publisher: $owner);
    $child = $child->fresh();

    $parent = Workflow::factory()->forWorkspace($workspace)->create();
    $parent->replaceGraph([
        'nodes' => [['key' => 'loop', 'type' => 'loop', 'config' => ['items_path' => 'input.items', 'workflow_id' => $child->id]]],
        'edges' => [],
    ]);
    $parent->publishVersion(publisher: $owner);
    $parent = $parent->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($parent, ['items' => []]);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->nodeRuns->firstWhere('key', 'loop')->output)->toBe(['results' => [], 'errors' => []]);
});

it('fails fast on the first item error when on_item_error is fail_fast', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $child = Workflow::factory()->forWorkspace($workspace)->create();
    $child->replaceGraph(['nodes' => [
        ['key' => 'c', 'type' => 'call_api', 'config' => ['method' => 'GET', 'url' => 'http://127.0.0.1:1/unreachable', 'timeout_seconds' => 1]],
    ], 'edges' => []]);
    $child->publishVersion(publisher: $owner);
    $child = $child->fresh();

    $parent = Workflow::factory()->forWorkspace($workspace)->create();
    $parent->replaceGraph([
        'nodes' => [['key' => 'loop', 'type' => 'loop', 'config' => [
            'items_path' => 'input.items',
            'workflow_id' => $child->id,
            'max_concurrent' => 1,
            'on_item_error' => 'fail_fast',
        ]]],
        'edges' => [],
    ]);
    $parent->publishVersion(publisher: $owner);
    $parent = $parent->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($parent, ['items' => [1, 2, 3]]);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Failed);
    $loopNode = $run->nodeRuns->firstWhere('key', 'loop');
    expect($loopNode->status)->toBe(NodeRunStatus::Failed);

    // fail_fast stops releasing further items once max_concurrent=1 serializes
    // execution — only the first item ever actually ran.
    expect(Run::where('parent_node_id', $loopNode->id)->count())->toBe(1);
});

it('tolerates item errors and still completes when on_item_error is continue', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    // A child that fails for every item — asserts "continue" still runs
    // every item and completes the loop (vs. "fail_fast" stopping early).
    $failingChild = Workflow::factory()->forWorkspace($workspace)->create();
    $failingChild->replaceGraph(['nodes' => [
        ['key' => 'c', 'type' => 'call_api', 'config' => ['method' => 'GET', 'url' => 'http://127.0.0.1:1/unreachable', 'timeout_seconds' => 1]],
    ], 'edges' => []]);
    $failingChild->publishVersion(publisher: $owner);
    $failingChild = $failingChild->fresh();

    $parent = Workflow::factory()->forWorkspace($workspace)->create();
    $parent->replaceGraph([
        'nodes' => [['key' => 'loop', 'type' => 'loop', 'config' => [
            'items_path' => 'input.items',
            'workflow_id' => $failingChild->id,
            'max_concurrent' => 1,
            'on_item_error' => 'continue',
        ]]],
        'edges' => [],
    ]);
    $parent->publishVersion(publisher: $owner);
    $parent = $parent->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($parent, ['items' => [1, 2]]);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);
    $loopNode = $run->nodeRuns->firstWhere('key', 'loop');
    expect($loopNode->status)->toBe(NodeRunStatus::Completed);
    expect(count($loopNode->output['errors']))->toBe(2);

    // continue policy releases every item despite failures.
    expect(Run::where('parent_node_id', $loopNode->id)->count())->toBe(2);
});
