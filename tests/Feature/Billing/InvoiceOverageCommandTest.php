<?php

use App\Actions\Billing\BillOverageCreditsAction;
use App\Exceptions\BillingAccountNotFoundException;
use App\Models\Billing\UsagePeriod;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\CreditOverage;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Log;

/**
 * Overage is metered as it is spent and invoiced afterwards, so what matters
 * here is that every accrued credit is billed exactly once — never twice on
 * a re-run, and never written off when Stripe refuses the charge.
 *
 * The action's single Stripe call is replaced with a recorder, so these
 * exercise the reserve/release bookkeeping around it rather than Cashier.
 */
class RecordingOverageBiller extends BillOverageCreditsAction
{
    /** @var list<array{description: string, amount_cents: int, metadata: array<string, string>}> */
    public array $invoiced = [];

    public ?Throwable $failWith = null;

    /**
     * @param  array<string, string>  $metadata
     */
    protected function invoice(Workspace $workspace, string $description, int $amountCents, array $metadata): ?string
    {
        $this->invoiced[] = [
            'description' => $description,
            'amount_cents' => $amountCents,
            'metadata' => $metadata,
        ];

        if ($this->failWith !== null) {
            throw $this->failWith;
        }

        return 'in_test_'.count($this->invoiced);
    }
}

function recordingBiller(?Throwable $failWith = null): RecordingOverageBiller
{
    $biller = new RecordingOverageBiller(app(CreditOverage::class));
    $biller->failWith = $failWith;

    app()->instance(BillOverageCreditsAction::class, $biller);

    return $biller;
}

function workspaceWithUnbilledOverage(int $credits, bool $withStripeCustomer = true): Workspace
{
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);

    $workspace->currentUsagePeriod()->forceFill([
        'ends_at' => now()->subDay(),
        'overage_credits_used' => $credits,
    ])->save();

    if ($withStripeCustomer) {
        $workspace->forceFill(['stripe_id' => 'cus_test_overage'])->save();
    }

    return $workspace->fresh();
}

function currentOveragePeriod(): UsagePeriod
{
    return UsagePeriod::query()->firstOrFail();
}

beforeEach(function () {
    config()->set('billing.credit_value_usd', 0.005);
    config()->set('billing.overage.minimum_invoice_cents', 50);
});

it('invoices a closed period for the overage it accrued', function () {
    $workspace = workspaceWithUnbilledOverage(1_000);
    $biller = recordingBiller();

    $result = $biller->execute($workspace, currentOveragePeriod());

    // 1,000 credits at $0.005 each is $5.00.
    expect($result['credits'])->toBe(1_000)
        ->and($result['amount_cents'])->toBe(500)
        ->and($result['invoice_id'])->toBe('in_test_1')
        ->and($biller->invoiced)->toHaveCount(1)
        ->and($biller->invoiced[0]['amount_cents'])->toBe(500)
        ->and($biller->invoiced[0]['metadata']['type'])->toBe('credit_overage');
});

it('marks the credits billed so a second run charges nothing', function () {
    $workspace = workspaceWithUnbilledOverage(1_000);
    $biller = recordingBiller();

    $biller->execute($workspace, currentOveragePeriod());
    $second = $biller->execute($workspace, currentOveragePeriod());

    expect($second)->toBeNull()
        ->and($biller->invoiced)->toHaveCount(1)
        ->and(currentOveragePeriod()->overage_credits_billed)->toBe(1_000);
});

it('bills only what accrued since the last invoice', function () {
    $workspace = workspaceWithUnbilledOverage(1_000);
    $biller = recordingBiller();

    $biller->execute($workspace, currentOveragePeriod());
    currentOveragePeriod()->increment('overage_credits_used', 400);

    $second = $biller->execute($workspace, currentOveragePeriod());

    expect($second['credits'])->toBe(400)
        ->and($second['amount_cents'])->toBe(200)
        ->and(currentOveragePeriod()->overage_credits_billed)->toBe(1_400);
});

it('leaves the credits unbilled when Stripe refuses the charge', function () {
    $workspace = workspaceWithUnbilledOverage(1_000);
    $biller = recordingBiller(new RuntimeException('card_declined'));

    expect(fn () => $biller->execute($workspace, currentOveragePeriod()))
        ->toThrow(RuntimeException::class);

    expect(currentOveragePeriod()->overage_credits_billed)->toBe(0);
});

it('does not open an invoice for less than the minimum worth billing', function () {
    $workspace = workspaceWithUnbilledOverage(20);
    $biller = recordingBiller();

    // 20 credits is $0.10 — held back until it is worth an invoice, not
    // written off, so the credits stay unbilled.
    expect($biller->execute($workspace, currentOveragePeriod()))->toBeNull()
        ->and($biller->invoiced)->toBeEmpty()
        ->and(currentOveragePeriod()->overage_credits_billed)->toBe(0);
});

it('refuses to bill a workspace that never became a Stripe customer', function () {
    $workspace = workspaceWithUnbilledOverage(1_000, withStripeCustomer: false);
    $biller = recordingBiller();

    expect(fn () => $biller->execute($workspace, currentOveragePeriod()))
        ->toThrow(BillingAccountNotFoundException::class);

    expect(currentOveragePeriod()->overage_credits_billed)->toBe(0);
});

it('invoices every closed period the command finds', function () {
    workspaceWithUnbilledOverage(1_000);
    $biller = recordingBiller();

    $this->artisan('billing:invoice-overage')
        ->expectsOutputToContain('Invoiced overage for 1 billing period(s).')
        ->assertSuccessful();

    expect($biller->invoiced)->toHaveCount(1)
        ->and(currentOveragePeriod()->overage_credits_billed)->toBe(1_000);
});

it('skips periods that are still accruing', function () {
    workspaceWithUnbilledOverage(1_000);
    currentOveragePeriod()->forceFill(['ends_at' => now()->addDay()])->save();
    $biller = recordingBiller();

    $this->artisan('billing:invoice-overage')
        ->expectsOutputToContain('Invoiced overage for 0 billing period(s).')
        ->assertSuccessful();

    expect($biller->invoiced)->toBeEmpty()
        ->and(currentOveragePeriod()->overage_credits_billed)->toBe(0);
});

it('logs and carries on when one workspace cannot be invoiced', function () {
    workspaceWithUnbilledOverage(1_000, withStripeCustomer: false);
    recordingBiller();

    Log::spy();

    $this->artisan('billing:invoice-overage')->assertSuccessful();

    Log::shouldHaveReceived('error')->once()->withArgs(
        fn (string $message): bool => $message === 'Failed to invoice credit overage.',
    );
});
