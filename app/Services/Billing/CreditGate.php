<?php

namespace App\Services\Billing;

use App\Enums\Notifications\AlertSeverity;
use App\Exceptions\InsufficientCreditsException;
use App\Models\Workspaces\Workspace;
use App\Notifications\Billing\CreditsExhaustedNotification;
use App\Services\Notifications\AdminAlerts;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\Cache;

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

    /**
     * How long a workspace goes without a repeat "you're out of credits"
     * notification — independent of `admin_alerts.enabled`, since that flag
     * governs operator alerting, not what customers are told.
     */
    private const int NOTIFICATION_THROTTLE_SECONDS = 3600;

    public function __construct(
        private readonly AdminAlerts $adminAlerts,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

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

        $this->notifyCreditsExhausted($workspace);
    }

    /**
     * Told once per throttle window, same as the admin alert above but on
     * its own key — this must fire even when `admin_alerts.enabled` is off,
     * since that flag is an operator kill switch, not a customer-facing one.
     */
    private function notifyCreditsExhausted(Workspace $workspace): void
    {
        $claimed = Cache::add(
            'credit-gate:exhausted-notified:'.$workspace->id,
            true,
            self::NOTIFICATION_THROTTLE_SECONDS,
        );

        if (! $claimed) {
            return;
        }

        $this->dispatcher->dispatch(
            $this->dispatcher->ownersAndAdmins($workspace),
            new CreditsExhaustedNotification($workspace),
        );
    }
}
