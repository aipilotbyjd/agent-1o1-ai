<?php

namespace App\Services\Billing;

use App\Models\Workspaces\Workspace;

/**
 * Credits or debits a workspace's Stripe customer balance — money off (or
 * onto) its next invoice, never a payout. Wrapped so the referral program's
 * `StripeBalanceRewardHandler` can be exercised without calling Stripe.
 */
class StripeCustomerBalance
{
    /**
     * @return string The Stripe customer balance transaction id.
     */
    public function credit(Workspace $workspace, int $amountCents, string $description): string
    {
        $workspace->createOrGetStripeCustomer();

        return $workspace->creditBalance($amountCents, $description)->id;
    }

    /**
     * @return string The Stripe customer balance transaction id.
     */
    public function debit(Workspace $workspace, int $amountCents, string $description): string
    {
        $workspace->createOrGetStripeCustomer();

        return $workspace->debitBalance($amountCents, $description)->id;
    }
}
