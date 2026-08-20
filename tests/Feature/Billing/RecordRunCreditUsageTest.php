<?php

use App\Actions\Agents\CreateAgentSessionAction;
use App\Actions\Agents\SendAgentMessageAction;
use App\Actions\Workflows\StartWorkflowRunAction;
use App\Ai\Agents\AdHocPromptAgent;
use App\Ai\Agents\WorkspaceAgent;
use App\Enums\Billing\CreditTransactionType;
use App\Events\Runs\RunCompleted;
use App\Listeners\Workflows\RecordRunCreditUsage;
use App\Models\Agents\Agent;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\Plan;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;

it('charges one credit_transaction per completed node run', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'a', 'to' => 'b']],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $run = $run->fresh(['nodeRuns']);

    $transactions = CreditTransaction::where('workspace_id', $workspace->id)
        ->where('source_type', CreditTransactionType::NodeRun)
        ->get();

    expect($transactions)->toHaveCount(2);
    expect($transactions->pluck('source_id')->sort()->values()->all())
        ->toBe($run->nodeRuns->pluck('id')->sort()->values()->all());
    expect($transactions->every(fn ($t) => $t->credits === 1))->toBeTrue();

    expect($workspace->currentUsagePeriod()->credits_used)->toBe(2);
});

it('persists a node run\'s usage so it feeds CreditMeter\'s token-based cost', function () {
    AdHocPromptAgent::fake(['a canned reply']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'ask_ai', 'config' => ['prompt' => 'hi']]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $run = $run->fresh(['nodeRuns']);

    $transaction = CreditTransaction::where('source_type', CreditTransactionType::NodeRun)->sole();

    expect($transaction->credits)->toBeGreaterThanOrEqual(1);
    expect($run->nodeRuns->first()->usage)->not->toBeNull();
});

it('charges one credit_transaction per agent turn', function () {
    WorkspaceAgent::fake(['Hello there!']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);

    $reply = app(SendAgentMessageAction::class)->execute($session, 'Hi!');

    $transaction = CreditTransaction::where('source_type', CreditTransactionType::AgentStep)->sole();

    expect($transaction->source_id)->toBe($reply->id);
    expect($transaction->workspace_id)->toBe($workspace->id);
    expect($workspace->currentUsagePeriod()->credits_used)->toBe($transaction->credits);
});

it('does not double-bill a run whose charge listener is replayed', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'a', 'to' => 'b']],
    ]);
    $workflow->publishVersion(publisher: $owner);

    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh());

    expect($workspace->currentUsagePeriod()->credits_used)->toBe(2);

    // The listener is queued and bills node runs one at a time, so a retry
    // after a mid-loop failure re-enters it with charges already written.
    app(RecordRunCreditUsage::class)->handle(new RunCompleted($run->fresh()));
    app(RecordRunCreditUsage::class)->handle(new RunCompleted($run->fresh()));

    expect(CreditTransaction::where('workspace_id', $workspace->id)->count())->toBe(2);
    expect($workspace->currentUsagePeriod()->credits_used)->toBe(2);
});

it('bills a run that overruns the remaining balance rather than losing the record', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 1]);
    config(['billing.default_plan' => 'free']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'a', 'to' => 'b']],
    ]);
    $workflow->publishVersion(publisher: $owner);

    // One credit left, a two-node run: the gate lets it start, and both nodes
    // are billed even though the second overruns.
    app(StartWorkflowRunAction::class)->execute($workflow->fresh());

    expect(CreditTransaction::where('workspace_id', $workspace->id)->count())->toBe(2);
    expect($workspace->currentUsagePeriod()->credits_used)->toBe(2);
    expect($workspace->fresh()->availableCredits())->toBe(0);
});
