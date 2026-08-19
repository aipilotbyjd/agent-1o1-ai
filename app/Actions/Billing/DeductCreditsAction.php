<?php

namespace App\Actions\Billing;

use App\Enums\Billing\CreditTransactionType;
use App\Enums\Notifications\AlertSeverity;
use App\Exceptions\InsufficientCreditsException;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\UsagePeriod;
use App\Models\Workspaces\Workspace;
use App\Services\Notifications\AdminAlerts;
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
 * Being the single metering seam also makes it the natural place to raise the
 * two usage admin alerts. Both are raised *outside* the transaction: the
 * period row is locked inside it, and no notification work may run while that
 * lock is held.
 */
class DeductCreditsAction
{
    public function __construct(private readonly AdminAlerts $adminAlerts) {}

    public function execute(
        Workspace $workspace,
        CreditTransactionType $sourceType,
        int $sourceId,
        int $credits,
        ?string $reason = null,
    ): CreditTransaction {
        try {
            /** @var array{transaction: CreditTransaction, credits_before: int, credits_after: int, credits_limit: int|null} $result */
            $result = DB::transaction(function () use ($workspace, $sourceType, $sourceId, $credits, $reason): array {
                $period = $workspace->currentUsagePeriod();
                $lockedPeriod = UsagePeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();

                if (! $lockedPeriod->hasRemainingCredits($credits)) {
                    throw new InsufficientCreditsException($credits, max(0, $lockedPeriod->credits_limit - $lockedPeriod->credits_used));
                }

                $transaction = $workspace->creditTransactions()->create([
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'credits' => $credits,
                    'reason' => $reason,
                ]);

                $creditsBefore = $lockedPeriod->credits_used;

                $lockedPeriod->increment('credits_used', $credits);

                return [
                    'transaction' => $transaction,
                    'credits_before' => $creditsBefore,
                    'credits_after' => $creditsBefore + $credits,
                    'credits_limit' => $lockedPeriod->credits_limit,
                ];
            });
        } catch (InsufficientCreditsException $exception) {
            $this->alertCreditsExhausted($workspace, $credits, $exception);

            throw $exception;
        }

        $this->alertUsageThresholdCrossed(
            $workspace,
            $result['credits_before'],
            $result['credits_after'],
            $result['credits_limit'],
        );

        return $result['transaction'];
    }

    /**
     * Only the charge that takes a workspace across the configured percentage
     * alerts — every later charge in the same period is already above the line
     * and stays silent, independently of the throttle window.
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

        $this->adminAlerts->raise(
            key: 'usage.threshold_crossed',
            title: "{$workspace->name} crossed {$percent}% of its credit limit",
            body: "{$creditsAfter} of {$creditsLimit} credits used in the current period.",
            context: [
                'workspace_id' => $workspace->id,
                'credits_used' => $creditsAfter,
                'credits_limit' => $creditsLimit,
                'percent_used' => (int) floor($creditsAfter / $creditsLimit * 100),
            ],
            severity: AlertSeverity::Warning,
            throttleKey: "usage.threshold_crossed:{$workspace->id}",
        );
    }

    /**
     * The workspace is now blocked — every further charge fails until credits
     * are topped up, so this is graded Critical rather than Warning.
     */
    private function alertCreditsExhausted(
        Workspace $workspace,
        int $credits,
        InsufficientCreditsException $exception,
    ): void {
        $this->adminAlerts->raise(
            key: 'usage.limit_exhausted',
            title: "{$workspace->name} is blocked — credit limit exhausted",
            body: "A charge of {$credits} credits was rejected with {$exception->creditsAvailable} remaining.",
            context: [
                'workspace_id' => $workspace->id,
                'credits_needed' => $exception->creditsNeeded,
                'credits_available' => $exception->creditsAvailable,
            ],
            severity: AlertSeverity::Critical,
            throttleKey: "usage.limit_exhausted:{$workspace->id}",
        );
    }
}
