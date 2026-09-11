<?php

namespace App\Actions\Billing;

use App\Exceptions\BillingAccountNotFoundException;
use App\Models\Billing\UsagePeriod;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\CreditOverage;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Turns a period's accrued overage into a Stripe invoice — the second half
 * of Gumloop's "keep running past your monthly credits, billed at $0.005 per
 * credit". Overage is metered as it is spent (`DeductCreditsAction` writes
 * `usage_periods.overage_credits_used`) and charged after the fact, because
 * invoicing per node run would mean a Stripe call inside the metering path.
 *
 * The credits are marked billed *before* the Stripe call and unmarked if it
 * throws, rather than the other way round. Both orderings can lose to a
 * crash; this one loses unbilled revenue, the other double-charges a
 * customer, and only one of those is recoverable by looking at the ledger.
 */
class BillOverageCreditsAction
{
    public function __construct(private readonly CreditOverage $overage) {}

    /**
     * Bills everything `$period` has accrued and not yet been invoiced for.
     * Returns `null` when there is nothing to do — no unbilled overage, or
     * too little of it to be worth an invoice — so a caller can tell "billed
     * nothing" from "billed zero credits".
     *
     * @return array{credits: int, amount_cents: int, invoice_id: string|null}|null
     *
     * @throws BillingAccountNotFoundException when the workspace never became
     *                                         a Stripe customer, which means
     *                                         its overage cannot be collected.
     */
    public function execute(Workspace $workspace, UsagePeriod $period): ?array
    {
        $minimumCents = (int) config('billing.overage.minimum_invoice_cents');
        $credits = $period->unbilledOverageCredits();

        if ($credits <= 0 || $this->overage->priceInCents($credits) < $minimumCents) {
            return null;
        }

        if (! $workspace->hasStripeId()) {
            throw new BillingAccountNotFoundException(
                'This workspace accrued overage credits but has no Stripe customer to invoice.',
            );
        }

        $reserved = $this->reserve($period, $credits);

        if ($reserved <= 0) {
            return null;
        }

        $amountCents = $this->overage->priceInCents($reserved);

        // Another run may have claimed most of it between the two reads,
        // leaving too little to be worth an invoice after all.
        if ($amountCents < $minimumCents) {
            $this->release($period, $reserved);

            return null;
        }

        try {
            $invoiceId = $this->invoice(
                $workspace,
                $this->description($reserved, $period),
                $amountCents,
                [
                    'type' => 'credit_overage',
                    'usage_period_id' => (string) $period->id,
                    'overage_credits' => (string) $reserved,
                ],
            );
        } catch (Throwable $e) {
            $this->release($period, $reserved);

            throw $e;
        }

        return [
            'credits' => $reserved,
            'amount_cents' => $amountCents,
            'invoice_id' => $invoiceId,
        ];
    }

    /**
     * The one Stripe call this action makes: a single invoice line for the
     * period's overage, charged to the workspace's default payment method
     * through Cashier. Isolated behind its own method so the reserve/release
     * bookkeeping around it can be exercised without a Stripe account.
     *
     * @param  array<string, string>  $metadata
     */
    protected function invoice(Workspace $workspace, string $description, int $amountCents, array $metadata): ?string
    {
        return $workspace->invoiceFor($description, $amountCents, [], ['metadata' => $metadata])->id ?? null;
    }

    /**
     * Claims the credits under the period's row lock so two concurrent runs
     * can't invoice the same overage twice. Re-reads inside the lock and
     * returns what was actually claimed, which is less than `$credits` when
     * another run got there first.
     */
    private function reserve(UsagePeriod $period, int $credits): int
    {
        return DB::transaction(function () use ($period, $credits): int {
            $locked = UsagePeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();

            $claimable = min($credits, $locked->unbilledOverageCredits());

            if ($claimable > 0) {
                $locked->increment('overage_credits_billed', $claimable);
                $period->refresh();
            }

            return $claimable;
        });
    }

    /**
     * Puts credits back on the unbilled pile after a failed Stripe call, so
     * the next run retries them instead of writing them off.
     */
    private function release(UsagePeriod $period, int $credits): void
    {
        DB::transaction(function () use ($period, $credits): void {
            UsagePeriod::whereKey($period->id)->lockForUpdate()->firstOrFail()
                ->decrement('overage_credits_billed', $credits);
        });

        $period->refresh();
    }

    private function description(int $credits, UsagePeriod $period): string
    {
        $window = $period->starts_at->format('M j').' – '.$period->ends_at->clone()->subSecond()->format('M j, Y');

        return number_format($credits).' overage credits ('.$window.')';
    }
}
