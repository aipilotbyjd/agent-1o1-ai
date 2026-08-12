<?php

namespace App\Services\Workflows;

use App\Enums\NodeRunStatus;
use App\Enums\Workflows\FlowControlNodeType;
use App\Jobs\Workflows\DispatchNextNodesJob;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\WorkflowApproval;
use App\Nodes\FlowLogic\DelayNode;
use App\Notifications\Workspace\RunApprovalRequestedNotification;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Workflows\Engine\LoopCoordinator;
use App\Services\Workflows\Engine\StepFailureHandler;
use App\Services\Workflows\Engine\SubWorkflowCoordinator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The engine's single entry point — callers (jobs, controllers, listeners)
 * depend only on this, not on `GraphAdvancer`/`StepFailureHandler`/the
 * coordinators directly. Ported design from docs/WORKFLOWS_PLAN.md's
 * `WorkflowRunner` section. Graph traversal after a node settles is handed
 * off to `DispatchNextNodesJob` (its own `Queue::WorkflowDispatch` queue)
 * rather than called in-process, so `GraphAdvancer` work never runs on the
 * same queue/supervisor as node execution — see docs/STRUCTURE.md's "Queues
 * & Horizon" table.
 */
class WorkflowRunner
{
    public function __construct(
        private readonly NodeRegistry $registry,
        private readonly StepFailureHandler $failureHandler,
        private readonly SubWorkflowCoordinator $subWorkflowCoordinator,
        private readonly LoopCoordinator $loopCoordinator,
        private readonly TemplateResolver $templateResolver,
        private readonly NotificationDispatcher $notifications,
    ) {}

    public function executeStep(NodeRun $nodeRun): void
    {
        // A retried or racing dispatch landing on an already-settled node is
        // a no-op, not a bug — the unique (run_id, key) constraint already
        // prevented a duplicate row; this guards the same node being
        // executed twice by two racing workers.
        if ($nodeRun->status !== NodeRunStatus::Pending) {
            return;
        }

        $run = $nodeRun->run;
        $graph = $run->workflowVersion->graph;
        $context = $this->buildContext($run, $nodeRun);
        $nodeDefinition = $this->resolvedNodeDefinition($graph, $nodeRun->key, $context);

        match ($nodeRun->type) {
            FlowControlNodeType::HumanApproval->value => $this->pauseForApproval($run, $nodeRun),
            FlowControlNodeType::Wait->value => $this->pauseForWait($nodeRun, $nodeDefinition),
            FlowControlNodeType::SubWorkflow->value => $this->subWorkflowCoordinator->start($run, $nodeRun, $nodeDefinition),
            FlowControlNodeType::Loop->value => $this->loopCoordinator->start($run, $nodeRun, $nodeDefinition, $context),
            // GraphAdvancer::joinReadiness() only ever dispatches a
            // join_paths node once it's genuinely ready — by the time
            // WorkflowRunner sees it, there's nothing left to do but settle
            // it immediately and let traversal continue.
            FlowControlNodeType::JoinPaths->value => $this->completeJoin($run, $nodeRun),
            default => $this->executeNodeContract($run, $nodeRun, $nodeDefinition, $graph, $context),
        };
    }

    /**
     * Node config is templated (`{{ input.name }}`, `{{ nodes.a.result }}`)
     * against the run's context once here, before any of `executeStep()`'s
     * branches see it — see `TemplateResolver`.
     *
     * @param  array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>}>, edges: array<int, array{from: string, to: string, condition: string|null}>}  $graph
     * @param  array<string, mixed>  $context
     * @return array{key: string, type: string, config: array<string, mixed>}
     */
    private function resolvedNodeDefinition(array $graph, string $key, array $context): array
    {
        $nodeDefinition = collect($graph['nodes'])->firstWhere('key', $key) ?? ['key' => $key, 'type' => null, 'config' => []];

        return [
            ...$nodeDefinition,
            'config' => $this->templateResolver->resolve($nodeDefinition['config'] ?? [], $context),
        ];
    }

    /**
     * @param  array{key: string, type: string, config: array<string, mixed>}  $nodeDefinition
     * @param  array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>}>, edges: array<int, array{from: string, to: string, condition: string|null}>}  $graph
     * @param  array<string, mixed>  $context
     */
    private function executeNodeContract(Run $run, NodeRun $nodeRun, array $nodeDefinition, array $graph, array $context): void
    {
        $nodeRun->forceFill(['status' => NodeRunStatus::Running, 'started_at' => now()])->save();

        try {
            $node = $this->registry->resolve($nodeRun->type);

            $output = $node->execute($run, $nodeDefinition['config'] ?? [], $context);

            $nodeRun->forceFill([
                'status' => NodeRunStatus::Completed,
                'output' => $output,
                // Nodes that call an LLM (AskAiNode) report a `usage` key in
                // their output — lifted onto the dedicated column too, so
                // `CreditMeter`/reporting can read it without knowing which
                // node types happen to embed it in their output shape.
                'usage' => $output['usage'] ?? null,
                'finished_at' => now(),
            ])->save();

            // DelayNode reports how long to wait rather than blocking a
            // worker inside execute() — the engine applies the real delay
            // to the next dispatch instead.
            $delaySeconds = $nodeRun->type === (new DelayNode)->type() ? (int) ($output['seconds'] ?? 0) : 0;

            $pending = DispatchNextNodesJob::dispatch($run->id, $nodeRun->id);

            if ($delaySeconds > 0) {
                $pending->delay(now()->addSeconds($delaySeconds));
            }
        } catch (Throwable $e) {
            Log::warning('Workflow node execution failed.', [
                'run_id' => $run->id,
                'node_key' => $nodeRun->key,
                'node_type' => $nodeRun->type,
                'attempt' => $nodeRun->attempt,
                'exception' => $e->getMessage(),
            ]);

            $this->failureHandler->handle($run, $nodeRun, $nodeDefinition, $graph, $e);
        }
    }

    private function completeJoin(Run $run, NodeRun $nodeRun): void
    {
        $nodeRun->forceFill([
            'status' => NodeRunStatus::Completed,
            'started_at' => now(),
            'output' => [],
            'finished_at' => now(),
        ])->save();

        DispatchNextNodesJob::dispatch($run->id, $nodeRun->id);
    }

    private function pauseForApproval(Run $run, NodeRun $nodeRun): void
    {
        $nodeRun->forceFill(['status' => NodeRunStatus::AwaitingApproval, 'started_at' => now()])->save();

        $approval = $nodeRun->approval()->create([
            'run_id' => $run->id,
            'node_run_id' => $nodeRun->id,
            'requested_at' => now(),
        ]);

        $this->notifications->dispatch(
            $this->notifications->ownersAndAdmins($run->workspace),
            new RunApprovalRequestedNotification($run->workspace, $run, $nodeRun, $approval),
        );
    }

    /**
     * @param  array{key: string, type: string, config: array<string, mixed>}  $nodeDefinition
     */
    private function pauseForWait(NodeRun $nodeRun, array $nodeDefinition): void
    {
        $config = $nodeDefinition['config'] ?? [];
        $timeoutSeconds = $config['timeout_seconds'] ?? null;

        $nodeRun->forceFill([
            'status' => NodeRunStatus::AwaitingCallback,
            'started_at' => now(),
            'callback_token' => Str::random(64),
            'callback_expires_at' => $timeoutSeconds !== null ? now()->addSeconds((int) $timeoutSeconds) : null,
        ])->save();
    }

    /**
     * Idempotent: a second decision on an already-decided approval is a
     * silent no-op rather than an error, so a double-submitted click can't
     * fail loudly.
     */
    public function resolveApproval(WorkflowApproval $approval, bool $approved, ?User $decidedBy = null, ?string $message = null): void
    {
        if ($approval->isDecided()) {
            return;
        }

        $approval->forceFill([
            'decided_at' => now(),
            'decided_by' => $decidedBy?->id,
            'decision' => $approved ? 'approved' : 'rejected',
            'message' => $message,
        ])->save();

        $nodeRun = $approval->nodeRun;
        $run = $nodeRun->run;

        if ($approved) {
            $nodeRun->forceFill(['status' => NodeRunStatus::Completed, 'output' => ['approved' => true], 'finished_at' => now()])->save();
            DispatchNextNodesJob::dispatch($run->id, $nodeRun->id);

            return;
        }

        $nodeRun->forceFill([
            'status' => NodeRunStatus::Failed,
            'error' => 'Approval rejected.'.($message !== null ? " {$message}" : ''),
            'finished_at' => now(),
        ])->save();

        $this->failureHandler->routeFailureOrFailRun($run, $nodeRun, $run->workflowVersion->graph);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ModelNotFoundException
     */
    public function resolveCallback(string $token, array $payload = []): NodeRun
    {
        $nodeRun = NodeRun::where('callback_token', $token)
            ->where('status', NodeRunStatus::AwaitingCallback)
            ->firstOrFail();

        $run = $nodeRun->run;

        // Single-use: null the token out so a redelivered/replayed callback
        // 404s on the second attempt instead of double-resolving.
        $nodeRun->forceFill([
            'status' => NodeRunStatus::Completed,
            'output' => $payload,
            'finished_at' => now(),
            'callback_token' => null,
        ])->save();

        DispatchNextNodesJob::dispatch($run->id, $nodeRun->id);

        return $nodeRun;
    }

    public function expireWait(NodeRun $nodeRun): void
    {
        if ($nodeRun->status !== NodeRunStatus::AwaitingCallback) {
            return;
        }

        $run = $nodeRun->run;
        $graph = $run->workflowVersion->graph;
        $context = $this->buildContext($run, $nodeRun);
        $nodeDefinition = $this->resolvedNodeDefinition($graph, $nodeRun->key, $context);
        $continueOnTimeout = (bool) ($nodeDefinition['config']['continue_on_timeout'] ?? false);

        if ($continueOnTimeout) {
            $nodeRun->forceFill([
                'status' => NodeRunStatus::Completed,
                'output' => ['timed_out' => true],
                'finished_at' => now(),
                'callback_token' => null,
            ])->save();

            DispatchNextNodesJob::dispatch($run->id, $nodeRun->id);

            return;
        }

        $nodeRun->forceFill([
            'status' => NodeRunStatus::Failed,
            'error' => 'Wait timed out.',
            'finished_at' => now(),
            'callback_token' => null,
        ])->save();

        $this->failureHandler->routeFailureOrFailRun($run, $nodeRun, $graph);
    }

    /**
     * Called once a child `Run` (started by a `subflow` or `loop` node)
     * reaches a terminal state — resumes whichever coordinator owns the
     * parent node. See `Listeners\Workflows\{ResumeParentOnChildRunCompleted,
     * ResumeParentOnChildRunFailed}`.
     */
    public function resolveSubWorkflow(Run $childRun): void
    {
        $parentNodeRun = $childRun->parentNode;

        if ($parentNodeRun === null) {
            return;
        }

        match ($parentNodeRun->type) {
            FlowControlNodeType::SubWorkflow->value => $this->subWorkflowCoordinator->resume($childRun, $parentNodeRun),
            FlowControlNodeType::Loop->value => $this->loopCoordinator->resume($childRun, $parentNodeRun),
            default => null,
        };
    }

    /**
     * @return array{input: array<string, mixed>, nodes: array<string, mixed>}
     */
    private function buildContext(Run $run, NodeRun $nodeRun): array
    {
        $upstreamOutputs = $run->nodeRuns()
            ->where('status', NodeRunStatus::Completed)
            ->where('id', '!=', $nodeRun->id)
            ->pluck('output', 'key')
            ->all();

        return [
            'input' => $run->input ?? [],
            'nodes' => $upstreamOutputs,
        ];
    }
}
