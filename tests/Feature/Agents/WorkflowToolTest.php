<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Ai\Tools\WorkflowTool;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Tools\Request;

it('starts a workflow run and reports its status back to the model', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => [
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['echoed' => 'input.value']]],
    ], 'edges' => []]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $tool = new WorkflowTool($workflow, app(StartWorkflowRunAction::class));

    $result = json_decode($tool->handle(new Request(['input' => ['value' => 'hi']])), true);

    expect($result['status'])->toBe('completed');
    expect($result['output'])->toBe(['a' => ['echoed' => 'hi']]);
});
