<?php

use App\Actions\Referrals\AttributeReferralAction;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralProgram;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\StripeCustomerBalance;
use App\Services\Referrals\ReferralCodes;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        Client::query()->create([
            'id' => config('passport.password_client_id'),
            'name' => 'Testing Password Grant Client',
            'secret' => config('passport.password_client_secret'),
            'redirect_uris' => [],
            'grant_types' => ['password', 'refresh_token', 'social_exchange'],
            'revoked' => false,
        ]);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Referral program helpers
|--------------------------------------------------------------------------
*/

/**
 * A user who owns a workspace, the way a real signup leaves them.
 *
 * @param  array<string, mixed>  $attributes
 */
function referralUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes);

    app(WorkspaceService::class)->create($user, ['name' => "{$user->name}'s Workspace"]);

    return $user->fresh();
}

/**
 * Signs `$referred` (a fresh unverified user by default) up through
 * `$referrer`'s code under `$program` (a new default program by default).
 */
function referUser(User $referrer, ?User $referred = null, ?ReferralProgram $program = null): ?Referral
{
    $program ??= ReferralProgram::factory()->asDefault()->create();
    $code = app(ReferralCodes::class)->forUser($referrer);

    if (! $program->is_default) {
        $code->update(['program_id' => $program->id]);
    }

    return app(AttributeReferralAction::class)->execute($referred ?? referralUser(['email_verified_at' => null]), $code->code);
}

function platformAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_platform_admin' => true, 'two_factor_confirmed_at' => now()])->save();

    return $admin;
}

/**
 * Swaps Stripe customer-balance calls for an in-memory record.
 */
function fakeStripeBalance(): StripeCustomerBalance
{
    $fake = new class extends StripeCustomerBalance
    {
        /** @var list<array{workspace: string, cents: int}> */
        public array $credits = [];

        /** @var list<array{workspace: string, cents: int}> */
        public array $debits = [];

        public function credit(Workspace $workspace, int $amountCents, string $description): string
        {
            $this->credits[] = ['workspace' => $workspace->id, 'cents' => $amountCents];

            return 'cbtxn_'.count($this->credits);
        }

        public function debit(Workspace $workspace, int $amountCents, string $description): string
        {
            $this->debits[] = ['workspace' => $workspace->id, 'cents' => $amountCents];

            return 'cbtxn_debit_'.count($this->debits);
        }
    };

    app()->instance(StripeCustomerBalance::class, $fake);

    return $fake;
}

/**
 * A `invoice.payment_succeeded` event for `$customerId`.
 *
 * @param  array<string, mixed>  $invoice
 * @return array<string, mixed>
 */
function referralInvoicePaidPayload(string $eventId, string $customerId, int $amountPaid, array $invoice = []): array
{
    return [
        'id' => $eventId,
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => array_merge([
                'id' => 'in_'.$eventId,
                'customer' => $customerId,
                'amount_paid' => $amountPaid,
                'total' => $amountPaid,
                'total_excluding_tax' => $amountPaid,
                'payment_intent' => 'pi_'.$eventId,
                'currency' => 'usd',
                'billing_reason' => 'subscription_create',
                'lines' => ['data' => []],
            ], $invoice),
        ],
    ];
}
