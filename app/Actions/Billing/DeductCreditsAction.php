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
 */
class DeductCreditsAction
{
    public function execute(
        Workspace $workspace,
        CreditTransactionType $sourceType,
        int $sourceId,
        int $credits,
        ?string $reason = null,
    ): CreditTransaction {
        return DB::transaction(function () use ($workspace, $sourceType, $sourceId, $credits, $reason): CreditTransaction {
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

            $lockedPeriod->increment('credits_used', $credits);

            return $transaction;
        });
    }
}
