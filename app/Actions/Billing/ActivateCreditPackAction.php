<?php

namespace App\Actions\Billing;

use App\Enums\Billing\CreditPackStatus;
use App\Models\Billing\CreditPack;
use App\Models\Billing\UsagePeriod;
use Illuminate\Support\Facades\DB;

/**
 * Credits a workspace for a paid-for pack by raising its *current* usage
 * period's `credits_limit` — packs don't write to `credit_transactions`
 * (that ledger is consumption-only, see `DeductCreditsAction`). A `null`
 * limit means the plan is already unlimited, so there's nothing to raise.
 * Idempotent: safe to call more than once for the same pack (e.g. a
 * redelivered webhook), since `Active` packs are a no-op.
 */
class ActivateCreditPackAction
{
    public function execute(CreditPack $pack): void
    {
        if ($pack->status === CreditPackStatus::Active) {
            return;
        }

        DB::transaction(function () use ($pack): void {
            $period = $pack->workspace->currentUsagePeriod();
            $lockedPeriod = UsagePeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();

            if ($lockedPeriod->credits_limit !== null) {
                $lockedPeriod->increment('credits_limit', $pack->credits_amount);
            }

            $pack->update([
                'status' => CreditPackStatus::Active,
                'purchased_at' => now(),
            ]);
        });
    }
}
