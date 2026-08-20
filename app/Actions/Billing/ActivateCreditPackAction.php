<?php

namespace App\Actions\Billing;

use App\Enums\Billing\CreditPackStatus;
use App\Models\Billing\CreditPack;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Credits a workspace for a paid-for pack by adding to its non-expiring
 * `topup_credits` pool. Packs deliberately don't touch the current usage
 * period: raising `usage_periods.credits_limit` (as this used to) meant
 * purchased credits were thrown away at month rollover, were wiped outright
 * the next time `OpenUsagePeriodForSubscriptionAction` reset the limit from
 * the plan, and were silently dropped altogether on an unlimited period —
 * a customer paying and receiving nothing.
 *
 * Packs also don't write to `credit_transactions`; that ledger is
 * consumption-only (see `DeductCreditsAction`), and each charge records how
 * much of itself came out of this pool.
 *
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
            $workspace = Workspace::whereKey($pack->workspace_id)->lockForUpdate()->firstOrFail();

            $workspace->increment('topup_credits', $pack->credits_amount);

            $pack->update([
                'status' => CreditPackStatus::Active,
                'purchased_at' => now(),
            ]);
        });
    }
}
