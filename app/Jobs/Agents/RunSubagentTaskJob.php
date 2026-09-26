<?php

namespace App\Jobs\Agents;

use App\Enums\Agents\SubagentTaskStatus;
use App\Enums\Queue;
use App\Models\Agents\SubagentTask;
use App\Services\Agents\AgentRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Runs one `SubagentTask` as a normal chat turn in the subagent's own
 * conversation, so it is gated and billed like any other turn. Runs on its
 * own queue so several subagents work in parallel without competing with
 * trigger-fired agent runs for the `ai-agent` workers.
 *
 * The subagent is told what its siblings from the same parent are working
 * on, so clones splitting a job don't duplicate each other's work.
 */
class RunSubagentTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly string $taskId)
    {
        $this->onQueue(Queue::AiSubagent->value);
    }

    public function handle(AgentRunner $runner): void
    {
        $task = SubagentTask::query()->with('session')->find($this->taskId);

        if ($task === null || $task->status !== SubagentTaskStatus::Queued || $task->session === null) {
            return;
        }

        $task->forceFill(['status' => SubagentTaskStatus::Running, 'started_at' => now()])->save();

        try {
            $reply = $runner->run($task->session, $this->message($task), 'subagent');

            $task->forceFill([
                'status' => SubagentTaskStatus::Completed,
                'result' => $reply->content,
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $this->markFailed($task, $e);
        }
    }

    public function failed(?Throwable $e): void
    {
        $task = SubagentTask::query()->find($this->taskId);

        if ($task !== null && ! $task->status->isFinished()) {
            $this->markFailed($task, $e);
        }
    }

    private function markFailed(SubagentTask $task, ?Throwable $e): void
    {
        $task->forceFill([
            'status' => SubagentTaskStatus::Failed,
            'error' => $e?->getMessage() ?: 'The subagent stopped before finishing.',
            'finished_at' => now(),
        ])->save();
    }

    private function message(SubagentTask $task): string
    {
        $siblings = SubagentTask::query()
            ->with('agent:id,name')
            ->where('parent_session_id', $task->parent_session_id)
            ->whereKeyNot($task->id)
            ->whereIn('status', SubagentTaskStatus::activeValues())
            ->get()
            ->map(fn (SubagentTask $sibling): string => "- {$sibling->agent?->name}: {$sibling->task}");

        if ($siblings->isEmpty()) {
            return $task->task;
        }

        return $task->task
            ."\n\n---\nOther subagents are working on these at the same time; do not duplicate their work:\n"
            .$siblings->implode("\n");
    }
}
