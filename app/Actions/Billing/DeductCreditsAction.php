<?php

namespace App\Actions\Billing;

use App\Enums\Billing\CreditTransactionType;
use App\Enums\Notifications\AlertSeverity;
use App\Exceptions\InsufficientCreditsException;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\UsagePeriod;
use App\Models\Workspaces\Workspace;
use App\Notifications\Billing\CreditsLowNotification;
use App\Services\Notifications\AdminAlerts;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\DB;

/**
 * The only place that writes `credit_transactions`/increments
 * `usage_periods.credits_used` — everything else (the `RecordRunCreditUsage`
 * listener today; agent tool-call metering, usage endpoints, later) goes
 * through this action so "what does charging credits actually do" has one
 * implementation. Locks the usage period row so two concurrent charges
 * against the same workspace can't both pass a limit check that only one of
 * them should have.
 *
 * A charge draws on the period's plan allowance first and falls back to the
 * workspace's non-expiring `topup_credits` pool for the remainder, so
 * purchased credits are only consumed once the monthly allowance is gone.
 *
 * Idempotent per `(source_type, source_id)`: charging the same `NodeRun` or
 * `AgentMessage` twice returns the original ledger row untouched. The
 * listener runs on a queue and bills a run's node runs one at a time, so a
 * retry after a mid-loop failure would otherwise re-bill every node already
 * charged.
 *
 * Being the single metering seam also makes it the right place to raise the
 * `usage.threshold_crossed` admin alert. It is raised *outside* the
 * transaction — the period and workspace rows are locked inside it, and no
 * notification work may run while those locks are held. The companion
 * `usage.limit_exhausted` alert lives on `CreditGate` instead: charges here
 * run in overdraft mode for work that already executed, so this is no longer
 * where a workspace is actually refused.
 */
class DeductCreditsAction
{
    public function __construct(
        private readonly AdminAlerts $adminAlerts,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /**
     * @param  bool  $allowOverdraft  Record the charge even when it exceeds the
     *                                balance, instead of throwing. Callers
     *                                billing work that already ran pass `true`:
     *                                the compute is spent either way, so the
     *                                ledger should say so and the *next* run is
     *                                what gets refused by `CreditGate`.
     */
    public function execute(
        Workspace $workspace,
        CreditTransactionType $sourceType,
        int $sourceId,
        int $credits,
        ?string $reason = null,
        bool $allowOverdraft = false,
    ): CreditTransaction {
        /** @var array{transaction: CreditTransaction, charged: bool, credits_before: int, credits_after: int, credits_limit: int|null} $result */
        $result = DB::transaction(function () use ($workspace, $sourceType, $sourceId, $credits, $reason, $allowOverdraft): array {
            $existing = CreditTransaction::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->first();

            if ($existing !== null) {
                return [
                    'transaction' => $existing,
                    'charged' => false,
                    'credits_before' => 0,
                    'credits_after' => 0,
                    'credits_limit' => null,
                ];
            }

            $period = $workspace->currentUsagePeriod();
            $lockedPeriod = UsagePeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
            $lockedWorkspace = Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();

            $fromTopup = $this->topupPortion($lockedPeriod, $lockedWorkspace, $credits, $allowOverdraft);

            $transaction = $workspace->creditTransactions()->create([
                'usage_period_id' => $lockedPeriod->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'credits' => $credits,
                'topup_credits' => $fromTopup,
                'reason' => $reason,
            ]);

            $creditsBefore = $lockedPeriod->credits_used;

            $lockedPeriod->increment('credits_used', $credits);

            if ($fromTopup > 0) {
                $lockedWorkspace->decrement('topup_credits', $fromTopup);
            }

            return [
                'transaction' => $transaction,
                'charged' => true,
                'credits_before' => $creditsBefore,
                'credits_after' => $creditsBefore + $credits,
                'credits_limit' => $lockedPeriod->credits_limit,
            ];
        });

        if ($result['charged']) {
            $this->alertUsageThresholdCrossed(
                $workspace,
                $result['credits_before'],
                $result['credits_after'],
                $result['credits_limit'],
            );
        }

        return $result['transaction'];
    }

    /**
     * How much of `$credits` has to come out of the workspace's top-up pool
     * because the period's plan allowance can't cover it. Under overdraft the
     * pool is drained to zero and the rest simply lands on `credits_used`,
     * pushing it past the limit.
     *
     * @throws InsufficientCreditsException when plan allowance and top-up
     *                                      credits together fall short and
     *                                      overdraft is not allowed.
     */
    private function topupPortion(UsagePeriod $period, Workspace $workspace, int $credits, bool $allowOverdraft): int
    {
        if ($period->credits_limit === null) {
            return 0;
        }

        $shortfall = $credits - $period->remainingPlanCredits();

        if ($shortfall <= 0) {
            return 0;
        }

        if ($shortfall > $workspace->topup_credits) {
            if (! $allowOverdraft) {
                throw new InsufficientCreditsException(
                    $credits,
                    $period->remainingPlanCredits() + $workspace->topup_credits,
                );
            }

            return $workspace->topup_credits;
        }

        return $shortfall;
    }

    /**
     * Only the charge that takes a workspace across the configured percentage
     * of its plan allowance alerts — every later charge in the same period is
     * already above the line and stays silent, independently of the throttle
     * window. A repeat (idempotent) charge never reaches here, since it adds
     * no usage.
     */
    private function alertUsageThresholdCrossed(
        Workspace $workspace,
        int $creditsBefore,
        int $creditsAfter,
        ?int $creditsLimit,
    ): void {
        if ($creditsLimit === null || $creditsLimit <= 0) {
            return;
        }

        $percent = (int) config('admin_alerts.usage.threshold_percent');
        $thresholdCredits = (int) ceil($creditsLimit * $percent / 100);

        if ($creditsBefore >= $thresholdCredits || $creditsAfter < $thresholdCredits) {
            return;
        }

        $percentUsed = (int) floor($creditsAfter / $creditsLimit * 100);

        $this->adminAlerts->raise(
            key: 'usage.threshold_crossed',
            title: "{$workspace->name} crossed {$percent}% of its credit limit",
            body: "{$creditsAfter} of {$creditsLimit} plan credits used in the current period.",
            context: [
                'workspace_id' => $workspace->id,
                'credits_used' => $creditsAfter,
                'credits_limit' => $creditsLimit,
                'percent_used' => $percentUsed,
            ],
            severity: AlertSeverity::Warning,
            throttleKey: "usage.threshold_crossed:{$workspace->id}",
        );

        $this->dispatcher->dispatch(
            $this->dispatcher->ownersAndAdmins($workspace),
            new CreditsLowNotification($workspace, $creditsAfter, $creditsLimit, $percentUsed),
        );
    }
}
