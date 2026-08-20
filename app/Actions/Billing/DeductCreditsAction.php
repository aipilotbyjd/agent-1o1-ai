<?php

namespace App\Actions\Billing;

use App\Enums\Billing\CreditTransactionType;
use App\Exceptions\InsufficientCreditsException;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\UsagePeriod;
use App\Models\Workspaces\Workspace;
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
 */
class DeductCreditsAction
{
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
        return DB::transaction(function () use ($workspace, $sourceType, $sourceId, $credits, $reason, $allowOverdraft): CreditTransaction {
            $existing = CreditTransaction::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->first();

            if ($existing !== null) {
                return $existing;
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

            $lockedPeriod->increment('credits_used', $credits);

            if ($fromTopup > 0) {
                $lockedWorkspace->decrement('topup_credits', $fromTopup);
            }

            return $transaction;
        });
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
}
