<?php

namespace App\Ai\Tools;

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\RunStatus;
use App\Models\Runs\Run;
use App\Models\Workflows\Workflow;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Sleep;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * A `Workflow` attached as a tool — calls `StartWorkflowRunAction` the same
 * way a `subflow` node does (`SubWorkflowCoordinator`). The run executes on
 * the queue, so the tool waits up to `WAIT_SECONDS` for it to finish; a run
 * still going after that, or one paused for an approval or callback, is
 * reported back as in progress (with its id) rather than as a result.
 *
 * No formal per-workflow input schema exists yet (`ContractGenerator` is
 * deferred, per docs/WORKFLOWS_PLAN.md) — the model is given a single
 * free-form `input` object.
 */
class WorkflowTool implements Tool
{
    public const int WAIT_SECONDS = 30;

    /**
     * Providers cap tool names at 64 characters of `[A-Za-z0-9_-]`.
     */
    private const int MAX_NAME_LENGTH = 64;

    public function __construct(
        private readonly Workflow $workflow,
        private readonly StartWorkflowRunAction $startWorkflowRun,
    ) {}

    /**
     * `workflow_{slug}`, sanitised; a slug too long to fit is cut and
     * suffixed with the workflow id so two long slugs can't collide.
     */
    public function name(): string
    {
        $name = 'workflow_'.preg_replace('/[^A-Za-z0-9_-]/', '_', $this->workflow->slug);

        if (strlen($name) <= self::MAX_NAME_LENGTH) {
            return $name;
        }

        $suffix = "_{$this->workflow->id}";

        return substr($name, 0, self::MAX_NAME_LENGTH - strlen($suffix)).$suffix;
    }

    public function description(): Stringable|string
    {
        return $this->workflow->description ?? "Runs the '{$this->workflow->name}' workflow.";
    }

    public function handle(Request $request): Stringable|string
    {
        $run = $this->awaitRun($this->startWorkflowRun->execute(
            $this->workflow,
            (array) ($request->all('input')['input'] ?? []),
            triggerType: 'agent',
        ));

        $result = [
            'run_id' => $run->id,
            'status' => $run->status->value,
            'output' => $run->output,
        ];

        if (! $run->status->isTerminal()) {
            $result['note'] = 'The workflow has not finished yet; its output is not available in this conversation turn.';
        }

        return json_encode($result) ?: '{}';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'input' => $schema->object(fn () => [])->description('Input passed to the workflow run.'),
        ];
    }

    /**
     * Polls while the run is queued or executing. Waiting on an approval or
     * callback is left to the person or system that will provide it.
     */
    private function awaitRun(Run $run): Run
    {
        $deadline = now()->addSeconds(self::WAIT_SECONDS);

        while (in_array($run->status, [RunStatus::Pending, RunStatus::Running], true) && now()->lt($deadline)) {
            Sleep::for(500)->milliseconds();

            $run = $run->fresh();
        }

        return $run;
    }
}
