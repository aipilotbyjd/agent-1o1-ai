<?php

namespace App\Jobs\Agents;

use App\Enums\Queue;
use App\Exceptions\RunStateException;
use App\Models\Runs\Run;
use App\Services\Agents\AgentRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Continues a chat turn whose waiting actions have all been decided — see
 * `AgentRunner::resume()`. Whoever decided may have long since left, and a
 * triggered turn never had anyone watching, so this runs on the queue; an
 * open chat follows along through the conversation's broadcasts.
 *
 * A turn someone already resumed (a streamed resume, a second decision
 * landing at once) is skipped rather than retried.
 */
class ResumeAgentTurnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly string $runId)
    {
        $this->onQueue(Queue::AiAgent->value);
    }

    public function handle(AgentRunner $runner): void
    {
        $run = Run::query()->find($this->runId);

        if ($run === null) {
            return;
        }

        try {
            $runner->resume($run);
        } catch (RunStateException) {
            // Already resumed, or still waiting on a decision.
        }
    }
}
