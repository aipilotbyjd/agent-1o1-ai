<?php

namespace App\Actions\Billing;

use App\Enums\Billing\CreditTransactionType;
use App\Enums\Notifications\AlertSeverity;
use App\Exceptions\InsufficientCreditsException;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\UsagePeriod;
use App\Models\Workspaces\Workspace;
use App\Notifications\Billing\CreditsLowNotification;
use App\Services\Billing\CreditOverage;
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
 * A charge draws on the period's plan allowance first, falls back to the
 * workspace's non-expiring `topup_credits` pool for the remainder, and only
 * then — where the workspace opted into credit overage — accrues the rest as
 * overage to be invoiced after the fact. Purchased credits are therefore
 * consumed before anything the workspace will be billed for again.
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
        private readonly CreditOverage $overage,
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
        string $sourceId,
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

            $split = $this->fundingSplit($lockedPeriod, $lockedWorkspace, $credits, $allowOverdraft);

            $transaction = $workspace->creditTransactions()->create([
                'usage_period_id' => $lockedPeriod->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'credits' => $credits,
                'topup_credits' => $split['topup'],
                'overage_credits' => $split['overage'],
                'reason' => $reason,
            ]);

            $creditsBefore = $lockedPeriod->credits_used;

            $lockedPeriod->increment('credits_used', $credits);

            if ($split['overage'] > 0) {
                $lockedPeriod->increment('overage_credits_used', $split['overage']);
            }

            if ($split['topup'] > 0) {
                $lockedWorkspace->decrement('topup_credits', $split['topup']);
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
     * Which pools fund `$credits`, in the order a workspace should want them
     * spent: the period's plan allowance first (free, and it expires), then
     * the non-expiring top-up pool it already paid for, then overage, which
     * is the only one that produces a new charge.
     *
     * The returned portions are what gets *recorded* — the plan share is the
     * remainder, so it is never stored. Anything past the overage cap is
     * deliberately not counted as overage: the workspace agreed to be billed
     * up to its cap and no further, so an overdrafting run's excess lands on
     * `credits_used` unbilled, exactly as it did before overage existed.
     *
     * @return array{topup: int, overage: int}
     *
     * @throws InsufficientCreditsException when the three pools together fall
     *                                      short and overdraft is not allowed.
     */
    private function fundingSplit(UsagePeriod $period, Workspace $workspace, int $credits, bool $allowOverdraft): array
    {
        if ($period->credits_limit === null) {
            return ['topup' => 0, 'overage' => 0];
        }

        $shortfall = $credits - $period->remainingPlanCredits();

        if ($shortfall <= 0) {
            return ['topup' => 0, 'overage' => 0];
        }

        $fromTopup = min($shortfall, $workspace->topup_credits);
        $uncovered = $shortfall - $fromTopup;

        if ($uncovered === 0) {
            return ['topup' => $fromTopup, 'overage' => 0];
        }

        $overageRemaining = $this->overage->remainingFor($workspace, $period);
        $fromOverage = $overageRemaining === null ? $uncovered : min($uncovered, $overageRemaining);

        if ($fromOverage < $uncovered && ! $allowOverdraft) {
            throw new InsufficientCreditsException(
                $credits,
                $period->remainingPlanCredits() + $workspace->topup_credits + $fromOverage,
            );
        }

        return ['topup' => $fromTopup, 'overage' => $fromOverage];
    }

    /**
     * The operator's line and the customer's lines are different questions
     * and are answered separately: `admin_alerts.usage.threshold_percent` is
     * one estate-wide number for whoever runs the platform, while a
     * workspace picks its own (Gumloop's 75% and 90% by default) for the
     * people who pay the bill.
     *
     * Either way only the charge that actually crosses a line fires — every
     * later charge in the same period is already above it and stays silent,
     * independently of the throttle window. A repeat (idempotent) charge
     * never reaches here, since it adds no usage.
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

        $percentUsed = (int) floor($creditsAfter / $creditsLimit * 100);

        $operatorPercent = (int) config('admin_alerts.usage.threshold_percent');

        if ($this->crossed($operatorPercent, $creditsBefore, $creditsAfter, $creditsLimit)) {
            $this->adminAlerts->raise(
                key: 'usage.threshold_crossed',
                title: "{$workspace->name} crossed {$operatorPercent}% of its credit limit",
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
        }

        $this->notifyUsageThresholdCrossed($workspace, $creditsBefore, $creditsAfter, $creditsLimit, $percentUsed);
    }

    /**
     * One charge can jump several of a workspace's thresholds at once (70% to
     * 95% crosses both 75 and 90). That is one piece of news, so it sends one
     * notification, for the highest line crossed — the lower ones would say
     * the same thing about the same balance.
     */
    private function notifyUsageThresholdCrossed(
        Workspace $workspace,
        int $creditsBefore,
        int $creditsAfter,
        int $creditsLimit,
        int $percentUsed,
    ): void {
        $crossed = array_filter(
            $workspace->creditUsageNotificationThresholds(),
            fn (int $percent): bool => $this->crossed($percent, $creditsBefore, $creditsAfter, $creditsLimit),
        );

        if ($crossed === []) {
            return;
        }

        $this->dispatcher->dispatch(
            $this->dispatcher->ownersAndAdmins($workspace),
            new CreditsLowNotification($workspace, $creditsAfter, $creditsLimit, $percentUsed, max($crossed)),
        );
    }

    /**
     * Whether this charge is the one that took usage from below `$percent` of
     * the allowance to at or above it.
     */
    private function crossed(int $percent, int $creditsBefore, int $creditsAfter, int $creditsLimit): bool
    {
        $thresholdCredits = (int) ceil($creditsLimit * $percent / 100);

        return $creditsBefore < $thresholdCredits && $creditsAfter >= $thresholdCredits;
    }
}
