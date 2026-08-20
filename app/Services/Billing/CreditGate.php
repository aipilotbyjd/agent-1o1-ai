<?php

namespace App\Services\Billing;

use App\Exceptions\InsufficientCreditsException;
use App\Models\Workspaces\Workspace;

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
 */
class CreditGate
{
    /**
     * Cost of the cheapest possible unit of billable work — one node run or
     * one agent turn that consumes no tokens. A workspace that can't cover
     * this can't usefully start anything.
     */
    private const int MINIMUM_CREDITS_TO_START = 1;

    /**
     * @throws InsufficientCreditsException
     */
    public function assertCanStartRun(Workspace $workspace): void
    {
        $available = $workspace->availableCredits();

        if ($available === null || $available >= self::MINIMUM_CREDITS_TO_START) {
            return;
        }

        throw new InsufficientCreditsException(self::MINIMUM_CREDITS_TO_START, $available);
    }
}
