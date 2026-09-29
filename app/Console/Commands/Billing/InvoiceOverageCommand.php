<?php

namespace App\Console\Commands\Billing;

use App\Actions\Billing\BillOverageCreditsAction;
use App\Models\Billing\UsagePeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use Throwable;

/**
 * Invoices the overage every closed billing period accrued. Runs daily
 * rather than at the moment a period ends, so a Stripe outage or a declined
 * card is retried on the next run instead of losing the charge — the credits
 * stay unbilled until an invoice actually succeeds.
 *
 * Only *ended* periods are billed. A period still accruing would need a
 * second invoice for whatever it spent afterwards, which is a worse
 * experience than one line at the end of the cycle and is not how Gumloop
 * bills it either.
 */
class InvoiceOverageCommand extends Command
{
    protected $signature = 'billing:invoice-overage';

    protected $description = 'Invoices workspaces for the credit overage their closed billing periods accrued.';

    public function __construct(private readonly BillOverageCreditsAction $billOverage)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $billed = 0;
        $failed = 0;

        $this->unbilledPeriods()->each(function (UsagePeriod $period) use (&$billed, &$failed): void {
            $workspace = $period->workspace;

            if ($workspace === null) {
                return;
            }

            try {
                $result = $this->billOverage->execute($workspace, $period);
            } catch (Throwable $e) {
                $failed++;

                Log::error('Failed to invoice credit overage.', [
                    'workspace_id' => $workspace->id,
                    'usage_period_id' => $period->id,
                    'unbilled_overage_credits' => $period->unbilledOverageCredits(),
                    'exception' => $e->getMessage(),
                ]);

                return;
            }

            if ($result !== null) {
                $billed++;
            }
        });

        $this->info("Invoiced overage for {$billed} billing period(s).");

        if ($failed > 0) {
            $this->warn("{$failed} billing period(s) could not be invoiced and will be retried.");
        }

        return self::SUCCESS;
    }

    /**
     * Closed periods that spent more overage than they have been invoiced
     * for. Chunked because this walks every workspace that ever ran on
     * overage, not just the current month's.
     *
     * @return LazyCollection<int, UsagePeriod>
     */
    private function unbilledPeriods(): LazyCollection
    {
        return UsagePeriod::query()
            ->where('ends_at', '<=', now())
            ->whereColumn('overage_credits_used', '>', 'overage_credits_billed')
            ->with('workspace')
            ->orderBy('id')
            ->lazyById();
    }
}
