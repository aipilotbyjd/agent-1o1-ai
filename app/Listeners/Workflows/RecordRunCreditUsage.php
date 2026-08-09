<?php

namespace App\Listeners\Workflows;

use App\Actions\Billing\DeductCreditsAction;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\NodeRunStatus;
use App\Events\Runs\RunCompleted;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use App\Models\Workflows\Workflow;
use App\Services\Billing\CreditMeter;

/**
 * Charges credits for a completed `Run` — one `CreditTransaction` per
 * completed/failed `NodeRun` for a Workflow run (a node routed through an
 * `error` edge still consumed real work), or one per turn (the assistant
 * reply) for an Agent session run. See
 * docs/WORKFLOWS_AGENTS_BUILD_PLAN.md Stage 8. Only fires on `RunCompleted`
 * — a `Run` that ends in `RunFailed` isn't charged for partial work yet,
 * left for a later pass.
 */
class RecordRunCreditUsage
{
    public function __construct(
        private readonly DeductCreditsAction $deductCredits,
        private readonly CreditMeter $meter,
    ) {}

    public function handle(RunCompleted $event): void
    {
        $run = $event->run->loadMissing('runnable');

        if ($run->runnable instanceof Workflow) {
            $this->chargeForWorkflowRun($run);

            return;
        }

        if ($run->runnable instanceof AgentSession) {
            $this->chargeForAgentTurn($run);
        }
    }

    private function chargeForWorkflowRun(Run $run): void
    {
        $nodeRuns = $run->nodeRuns()
            ->whereIn('status', [NodeRunStatus::Completed, NodeRunStatus::Failed])
            ->get();

        foreach ($nodeRuns as $nodeRun) {
            $this->deductCredits->execute(
                $run->workspace,
                CreditTransactionType::NodeRun,
                $nodeRun->id,
                $this->meter->costForNodeRun($nodeRun),
                "Node '{$nodeRun->key}' ({$nodeRun->type})",
            );
        }
    }

    private function chargeForAgentTurn(Run $run): void
    {
        $messageId = $run->output['message_id'] ?? null;
        $message = $messageId !== null ? AgentMessage::find($messageId) : null;

        if ($message === null) {
            return;
        }

        $this->deductCredits->execute(
            $run->workspace,
            CreditTransactionType::AgentStep,
            $message->id,
            $this->meter->costForAgentMessage($message),
            'Agent turn',
        );
    }
}
