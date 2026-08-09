<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Ai\Agents\EmbeddedAgent;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Models\Agents\Agent;
use App\Models\Billing\CreditTransaction;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;

it('resolves the templated prompt and returns the agent reply as node output', function () {
    EmbeddedAgent::fake(['The answer is 42.']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'ask', 'type' => 'agent', 'config' => ['agent_id' => $agent->id, 'prompt' => 'Echo {{ input.question }}']],
        ],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['question' => 'life, the universe, and everything']);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);
    $nodeRun = $run->nodeRuns->firstWhere('key', 'ask');
    expect($nodeRun->status)->toBe(NodeRunStatus::Completed);
    expect($nodeRun->output['text'])->toBe('The answer is 42.');
    expect($nodeRun->usage)->not->toBeNull();

    EmbeddedAgent::assertPrompted('Echo life, the universe, and everything');
});

it('defaults the prompt to input.message when config.prompt is omitted', function () {
    EmbeddedAgent::fake(['ok']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'ask', 'type' => 'agent', 'config' => ['agent_id' => $agent->id]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    app(StartWorkflowRunAction::class)->execute($workflow, ['message' => 'hello from input']);

    EmbeddedAgent::assertPrompted('hello from input');
});

it('feeds the node usage into the credit ledger like any other node', function () {
    EmbeddedAgent::fake(['ok']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'ask', 'type' => 'agent', 'config' => ['agent_id' => $agent->id, 'prompt' => 'hi']]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $nodeRun = $run->fresh(['nodeRuns'])->nodeRuns->first();

    $transaction = CreditTransaction::where('source_id', $nodeRun->id)->sole();
    expect($transaction->credits)->toBeGreaterThanOrEqual(1);
});

it('fails the node when the referenced agent does not belong to the workflow\'s workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $otherWorkspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);
    $foreignAgent = Agent::factory()->forWorkspace($otherWorkspace)->create();

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'ask', 'type' => 'agent', 'config' => ['agent_id' => $foreignAgent->id, 'prompt' => 'hi']]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Failed);
    expect($run->nodeRuns->first()->status)->toBe(NodeRunStatus::Failed);
});
