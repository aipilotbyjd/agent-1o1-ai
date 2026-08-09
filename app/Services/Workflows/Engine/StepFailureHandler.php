<?php

namespace App\Services\Workflows\Engine;

use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Events\Runs\RunFailed;
use App\Jobs\Workflows\DispatchNextNodesJob;
use App\Jobs\Workflows\ExecuteNodeJob;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\Workflows\WorkflowEdge;
use App\Services\Workflows\StepOptions;
use Throwable;

/**
 * Retry-vs-route-vs-fail policy for a failed `NodeRun` — ported design from
 * docs/WORKFLOWS_PLAN.md's `StepFailureHandler` section: exponential backoff
 * with jitter, redact credentials from the persisted error, and only route
 * down an `error` edge or fail the whole run once retries are exhausted.
 */
class StepFailureHandler
{
    /**
     * @param  array{key: string, type: string, config: array<string, mixed>}  $nodeDefinition
     * @param  array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>}>, edges: array<int, array{from: string, to: string, condition: string|null}>}  $graph
     */
    public function handle(Run $run, NodeRun $nodeRun, array $nodeDefinition, array $graph, Throwable $e): void
    {
        $options = StepOptions::fromNodeConfig($nodeDefinition['config'] ?? []);
        $message = $this->redact($e->getMessage());

        if ($nodeRun->attempt < $options->maxAttempts) {
            $this->retry($nodeRun, $options, $message);

            return;
        }

        $nodeRun->forceFill([
            'status' => NodeRunStatus::Failed,
            'error' => $message,
            'finished_at' => now(),
        ])->save();

        $this->routeFailureOrFailRun($run, $nodeRun, $graph, $options->continueOnError);
    }

    /**
     * Given an already-`Failed` `NodeRun`, either route down its `error`
     * edge (or, with `continue_on_error`, its unconditional edges), or fail
     * the whole run. Shared by the retries-exhausted path above and by
     * approval-rejection (`WorkflowRunner::resolveApproval()`) / wait-timeout
     * (`WorkflowRunner::expireWait()`), which fail a `NodeRun` without going
     * through a retry loop first.
     *
     * @param  array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>}>, edges: array<int, array{from: string, to: string, condition: string|null}>}  $graph
     */
    public function routeFailureOrFailRun(Run $run, NodeRun $nodeRun, array $graph, bool $continueOnError = false): void
    {
        $hasErrorEdge = collect($graph['edges'])->contains(
            fn (array $edge) => $edge['from'] === $nodeRun->key && $edge['condition'] === WorkflowEdge::ERROR_CONDITION,
        );

        if ($hasErrorEdge || $continueOnError) {
            DispatchNextNodesJob::dispatch($run->id, $nodeRun->id, $continueOnError && ! $hasErrorEdge);

            return;
        }

        $this->failRun($run, $nodeRun->error ?? 'Node failed.');
    }

    private function retry(NodeRun $nodeRun, StepOptions $options, string $message): void
    {
        $delay = $this->backoffSeconds($nodeRun->attempt, $options->retryDelaySeconds);

        $nodeRun->forceFill([
            'status' => NodeRunStatus::Pending,
            'attempt' => $nodeRun->attempt + 1,
            'error' => $message,
        ])->save();

        ExecuteNodeJob::dispatch($nodeRun->id)->delay(now()->addSeconds($delay));
    }

    /**
     * `min(base * 2^(attempt-1), 3600)` with ±25% jitter.
     */
    private function backoffSeconds(int $attempt, int $base): int
    {
        $base = max($base, 1);
        $exponential = min($base * (2 ** ($attempt - 1)), 3600);
        $jitter = $exponential * random_int(-25, 25) / 100;

        return max(0, (int) round($exponential + $jitter));
    }

    private function failRun(Run $run, string $message): void
    {
        $run->nodeRuns()
            ->whereIn('status', [NodeRunStatus::Pending, NodeRunStatus::Running])
            ->update(['status' => NodeRunStatus::Cancelled, 'finished_at' => now()]);

        $run->forceFill([
            'status' => RunStatus::Failed,
            'error' => $message,
            'finished_at' => now(),
        ])->save();

        event(new RunFailed($run));
    }

    /**
     * Minimal secret redaction — strips common credential-shaped substrings
     * before a message is ever persisted. Not the full `RunLogger::redact()`
     * port (docs/WORKFLOWS_PLAN.md); revisit once real credentials
     * (docs/PLAN.md Phase 6) flow through node execution.
     */
    private function redact(string $message): string
    {
        return preg_replace('/\b(?:sk|pk|rk)_[A-Za-z0-9_]{8,}\b/', '[redacted]', $message) ?? $message;
    }
}
