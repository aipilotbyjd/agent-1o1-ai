<?php

namespace App\Services\Billing;

use App\Enums\Notifications\AlertSeverity;
use App\Exceptions\InsufficientCreditsException;
use App\Models\Workspaces\Workspace;
use App\Services\Notifications\AdminAlerts;

/**
 * The pre-flight balance check. Credits are metered *after* the fact, on
 * `RunCompleted` — without a gate a workspace at zero still executed its
 * whole DAG (and paid for the LLM tokens) before the charge was even
 * attempted. This refuses the run up front instead, mapping to a 402 through
 * `bootstrap/app.php`.
 *
 * The check is deliberately coarse: a run's true cost isn't knowable until
 * its nodes have executed, so this only asserts the workspace can afford to
 * begin. A run that overruns the remaining balance still completes and is
 * billed in full (see `DeductCreditsAction`'s overdraft mode) — the *next*
 * run is the one that gets refused.
 *
 * Being the one place a workspace is actually refused, this is also where the
 * `usage.limit_exhausted` admin alert is raised.
 */
class CreditGate
{
    /**
     * Cost of the cheapest possible unit of billable work — one node run or
     * one agent turn that consumes no tokens. A workspace that can't cover
     * this can't usefully start anything.
     */
    private const int MINIMUM_CREDITS_TO_START = 1;

    public function __construct(private readonly AdminAlerts $adminAlerts) {}

    /**
     * @throws InsufficientCreditsException
     */
    public function assertCanStartRun(Workspace $workspace): void
    {
        $available = $workspace->availableCredits();

        if ($available === null || $available >= self::MINIMUM_CREDITS_TO_START) {
            return;
        }

        $this->alertCreditsExhausted($workspace, $available);

        throw new InsufficientCreditsException(self::MINIMUM_CREDITS_TO_START, $available);
    }

    /**
     * The workspace can no longer start work of any kind until its allowance
     * resets or it buys top-up credits, so this is graded Critical. Throttled
     * per workspace: every refused run re-detects the same condition.
     */
    private function alertCreditsExhausted(Workspace $workspace, int $available): void
    {
        $this->adminAlerts->raise(
            key: 'usage.limit_exhausted',
            title: "{$workspace->name} is blocked — out of credits",
            body: 'New workflow runs and agent turns are being refused until the plan allowance resets or top-up credits are purchased.',
            context: [
                'workspace_id' => $workspace->id,
                'credits_available' => $available,
            ],
            severity: AlertSeverity::Critical,
            throttleKey: "usage.limit_exhausted:{$workspace->id}",
        );
    }
}
