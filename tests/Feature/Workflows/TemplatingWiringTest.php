<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\RunStatus;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;

it('resolves {{ }} templates in a node config against the run input before execute() runs', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => 'run_code', 'config' => ['operations' => [
                ['op' => 'set', 'output' => 'greeting', 'value' => 'Hello, {{ input.name }}!'],
            ]]],
            ['key' => 'b', 'type' => 'run_code', 'config' => ['operations' => [
                ['op' => 'copy', 'output' => 'echoed', 'path' => 'input.name'],
                // Whole-string form referencing an upstream node's output —
                // resolved to the raw value, not a stringified copy.
                ['op' => 'set', 'output' => 'upstream', 'value' => '{{ nodes.a.greeting }}'],
            ]]],
        ],
        'edges' => [['from' => 'a', 'to' => 'b']],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['name' => 'Ada']);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->nodeRuns->firstWhere('key', 'a')->output)->toBe(['greeting' => 'Hello, Ada!']);
    expect($run->nodeRuns->firstWhere('key', 'b')->output)->toBe([
        'echoed' => 'Ada',
        'upstream' => 'Hello, Ada!',
    ]);
});
