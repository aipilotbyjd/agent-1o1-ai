<?php

namespace App\Events\Runs;

use App\Broadcasting\Channels;
use App\Models\Runs\Run;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A `Run` entered a new status. Fired from `RunObserver` rather than from the
 * engine, so every path that moves a run — `StartWorkflowRunAction`,
 * `GraphAdvancer::finishIfDone()`, `StepFailureHandler::failRun()`,
 * `WorkflowRunner::cancel()`, `AgentRunner` — publishes without having to
 * remember to.
 *
 * Distinct from the domain events `RunCompleted`/`RunFailed`/`RunCancelled`,
 * which exist for server-side listeners (billing, notifications) and carry
 * the whole model. This one is the wire format: a small, stable payload aimed
 * at a browser.
 */
class RunStateChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Run $run) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(Channels::workspaceRuns($this->run->workspace_id)),
            new PrivateChannel(Channels::run($this->run)),
        ];
    }

    public function broadcastAs(): string
    {
        return 'run.state-changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->run->id,
            'workflow_id' => $this->run->workflow_id,
            'runnable_type' => $this->run->runnable_type,
            'runnable_id' => $this->run->runnable_id,
            'status' => $this->run->status->value,
            'error' => $this->run->error,
            'started_at' => $this->run->started_at?->toIso8601String(),
            'finished_at' => $this->run->finished_at?->toIso8601String(),
        ];
    }
}
