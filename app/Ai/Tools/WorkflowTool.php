<?php

namespace App\Ai\Tools;

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Models\Workflows\Workflow;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * A `Workflow` attached as a tool — calls `StartWorkflowRunAction` the same
 * way a `subflow` node does (`SubWorkflowCoordinator`). Runs synchronously
 * (blocking the agent's tool-call step) — the async/webhook-callback mode
 * docs/PLAN.md's "Agent layer" section mentions for long-running workflows
 * is deferred; every run here is awaited inline.
 *
 * No formal per-workflow input schema exists yet (`ContractGenerator` is
 * deferred, per docs/WORKFLOWS_PLAN.md) — the model is given a single
 * free-form `input` object.
 */
class WorkflowTool implements Tool
{
    public function __construct(
        private readonly Workflow $workflow,
        private readonly StartWorkflowRunAction $startWorkflowRun,
    ) {}

    public function name(): string
    {
        return "workflow_{$this->workflow->slug}";
    }

    public function description(): Stringable|string
    {
        return $this->workflow->description ?? "Runs the '{$this->workflow->name}' workflow.";
    }

    public function handle(Request $request): Stringable|string
    {
        $run = $this->startWorkflowRun->execute(
            $this->workflow,
            (array) ($request->all('input')['input'] ?? []),
            triggerType: 'agent',
        );

        return json_encode([
            'run_id' => $run->id,
            'status' => $run->status->value,
            'output' => $run->output,
        ]) ?: '{}';
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
}
