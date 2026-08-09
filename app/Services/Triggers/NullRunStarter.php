<?php

namespace App\Services\Triggers;

use App\Contracts\Triggers\RunStarter;
use App\Models\Triggers\Trigger;

/**
 * Stand-in for the real run starter until `StartWorkflowRunAction`
 * (docs/WORKFLOWS_PLAN.md) or `AgentRunner` (docs/AGENTS_PLAN.md) exist. Bound
 * in AppServiceProvider::register() — swap that binding for a real
 * implementation the day either ships; nothing else in the trigger system
 * needs to change.
 */
class NullRunStarter implements RunStarter
{
    public function canRun(Trigger $trigger): bool
    {
        // Can't check "workflow is published" / "agent isn't deleted" without
        // the real target models — assume runnable so intake, dedupe, and
        // filtering can be built and tested today. The real implementation
        // tightens this once Workflow/Agent exist.
        return true;
    }

    public function isAlreadyRunning(Trigger $trigger): bool
    {
        return false;
    }

    public function start(Trigger $trigger, array $payload): int
    {
        // No workflow_runs/agent_sessions table exists yet — a synthetic id
        // lets the rest of the pipeline (claim, fire, mark fired) be exercised
        // for real rather than mocked out.
        return random_int(1, PHP_INT_MAX);
    }
}
