<?php

use App\Enums\Referrals\ReferralApprovalMode;
use App\Enums\Referrals\ReferralRecipient;
use App\Enums\Referrals\ReferralRewardStatus;
use App\Enums\Referrals\ReferralStatus;
use App\Enums\Referrals\ReferralTrigger;
use App\Models\Billing\Plan;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralBlockedDomain;
use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralReward;
use App\Models\Referrals\ReferralRewardRule;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Services\Referrals\ReferralCodes;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    fakeStripeBalance();

    $this->superAdmin = superAdmin();
    signInAsSuperAdmin($this->superAdmin);
});

function loggedFor(SuperAdmin $superAdmin, string $action): bool
{
    return SuperAdminAuditLog::query()->where('super_admin_id', $superAdmin->id)->where('action', $action)->exists();
}

function referralWithReward(bool $manualApproval = false): Referral
{
    $program = ReferralProgram::factory()->asDefault()->create([
        'require_verified_email' => false,
        'approval_mode' => $manualApproval ? ReferralApprovalMode::Manual : ReferralApprovalMode::Automatic,
    ]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::SignupVerified, ReferralRecipient::Referee)->credits(400)->create();

    return referUser(referralUser(), program: $program);
}

it('keeps the referral pages behind the super admin sign-in', function () {
    $this->post(route('super-admin.logout'));

    $this->get(route('super-admin.referrals.overview'))->assertRedirect(route('super-admin.login'));
    $this->get(route('super-admin.referrals.programs.index'))->assertRedirect(route('super-admin.login'));
});

it('renders every referral page', function () {
    $referral = referralWithReward();
    $program = $referral->program;
    $rule = $program->rules()->first();
    $code = app(ReferralCodes::class)->forUser(referralUser());

    foreach ([
        route('super-admin.referrals.overview'),
        route('super-admin.referrals.programs.index'),
        route('super-admin.referrals.programs.create'),
        route('super-admin.referrals.programs.show', $program),
        route('super-admin.referrals.programs.edit', $program),
        route('super-admin.referrals.programs.rules.create', $program),
        route('super-admin.referrals.rules.edit', $rule),
        route('super-admin.referrals.referrals.index'),
        route('super-admin.referrals.referrals.show', $referral),
        route('super-admin.referrals.rewards.index'),
        route('super-admin.referrals.rewards.create'),
        route('super-admin.referrals.rewards.create', ['user' => $referral->referred_user_id]),
        route('super-admin.referrals.codes.index'),
        route('super-admin.referrals.codes.edit', $code),
        route('super-admin.referrals.blocked-domains.index'),
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

it('creates a program from the form and logs it against the super admin', function () {
    $this->post(route('super-admin.referrals.programs.store'), [
        'name' => 'Partner program',
        'slug' => '',
        'is_active' => '1',
        'require_verified_email' => '1',
        'revoke_on_partial_refund' => '0',
        'attribution_window_days' => 60,
        'claim_window_hours' => 48,
        'activation_event' => 'first_successful_run',
        'activation_min_count' => 1,
        'activation_window_days' => 30,
        'approval_mode' => 'manual',
        'default_hold_days' => 14,
        'referrer_min_account_age_days' => 0,
        'referrer_eligible_plan_ids' => '',
        'fraud_checks' => ['shared_workspace' => '1', 'same_email_domain' => '0', 'disposable_email' => '1', 'card_fingerprint' => '0', 'ip_velocity' => ['enabled' => '1', 'max' => 3, 'hours' => 12], 'velocity_alert' => ['enabled' => '0', 'max_per_hour' => 20]],
    ])->assertRedirect();

    $program = ReferralProgram::query()->where('name', 'Partner program')->sole();

    expect($program->slug)->toBe('partner-program')
        ->and($program->approval_mode)->toBe(ReferralApprovalMode::Manual)
        ->and($program->attribution_window_days)->toBe(60)
        ->and($program->referrer_eligible_plan_ids)->toBeNull()
        ->and($program->fraudCheckEnabled('card_fingerprint'))->toBeFalse()
        ->and($program->fraudCheck('ip_velocity')['max'])->toBe(3)
        ->and(loggedFor($this->superAdmin, 'referral_program.created'))->toBeTrue();
});

it('saves unticked boxes as off when editing a program', function () {
    $program = ReferralProgram::factory()->create(['require_verified_email' => true]);
    $plan = Plan::factory()->create();

    $this->put(route('super-admin.referrals.programs.update', $program), [
        'name' => $program->name,
        'is_active' => '1',
        'require_verified_email' => '0',
        'referrer_eligible_plan_ids' => [$plan->id],
    ])->assertRedirect(route('super-admin.referrals.programs.show', $program));

    expect($program->fresh()->require_verified_email)->toBeFalse()
        ->and($program->fresh()->referrer_eligible_plan_ids)->toBe([$plan->id])
        ->and(loggedFor($this->superAdmin, 'referral_program.updated'))->toBeTrue();
});

it('makes a program the default and duplicates it', function () {
    ReferralProgram::factory()->asDefault()->create();
    $program = ReferralProgram::factory()->create(['name' => 'Spring']);

    $this->post(route('super-admin.referrals.programs.make-default', $program))->assertRedirect();
    expect($program->fresh()->is_default)->toBeTrue();

    $this->post(route('super-admin.referrals.programs.duplicate', $program))->assertRedirect();
    expect(ReferralProgram::query()->count())->toBe(3)
        ->and(loggedFor($this->superAdmin, 'referral_program.duplicated'))->toBeTrue();
});

it('adds a rule through the form, using the API consistency checks', function () {
    $program = ReferralProgram::factory()->create();
    $pro = Plan::factory()->create(['name' => 'Pro']);

    $this->post(route('super-admin.referrals.programs.rules.store', $program), [
        'name' => 'Missing plan',
        'is_active' => '1',
        'trigger' => 'first_payment',
        'recipient' => 'referrer',
        'reward_type' => 'plan_time',
        'duration_days' => 30,
    ])->assertSessionHasErrors();

    expect($program->rules()->count())->toBe(0);

    $this->post(route('super-admin.referrals.programs.rules.store', $program), [
        'name' => 'Month of Pro',
        'is_active' => '1',
        'trigger' => 'first_payment',
        'recipient' => 'referrer',
        'reward_type' => 'plan_time',
        'plan_id' => $pro->id,
        'duration_days' => 30,
        'if_already_on_plan' => 'grant_anyway',
        'conditions' => ['min_payment_cents' => '', 'billing_intervals' => ['yearly']],
    ])->assertRedirect(route('super-admin.referrals.programs.show', $program));

    $rule = $program->rules()->sole();

    expect($rule->conditions)->toBe(['billing_intervals' => ['yearly']])
        ->and(loggedFor($this->superAdmin, 'referral_rule.created'))->toBeTrue();

    $this->get(route('super-admin.referrals.programs.show', $program))->assertSee('30 days of Pro');
});

it('toggles and reorders rules', function () {
    $program = ReferralProgram::factory()->create();
    $first = ReferralRewardRule::factory()->forProgram($program)->credits(10)->create(['name' => 'First', 'sort_order' => 0]);
    $second = ReferralRewardRule::factory()->forProgram($program)->credits(20)->create(['name' => 'Second', 'sort_order' => 1]);

    $this->post(route('super-admin.referrals.rules.toggle', $first))->assertRedirect();
    expect($first->fresh()->is_active)->toBeFalse();

    $this->post(route('super-admin.referrals.rules.move', $second), ['direction' => 'up'])->assertRedirect();
    expect($program->rules()->orderBy('sort_order')->pluck('id')->all())->toBe([$second->id, $first->id]);
});

it('rejects a referral behind the password, clawing back its rewards', function () {
    $referral = referralWithReward();

    $this->post(route('super-admin.referrals.referrals.reject', $referral), ['reason' => 'Same person twice', 'current_password' => 'wrong'])
        ->assertSessionHasErrorsIn('reason', 'current_password');
    expect($referral->fresh()->status)->not->toBe(ReferralStatus::Rejected);

    $this->post(route('super-admin.referrals.referrals.reject', $referral), ['reason' => 'Same person twice', 'current_password' => 'password'])
        ->assertRedirect(route('super-admin.referrals.referrals.show', $referral));

    expect($referral->fresh()->status)->toBe(ReferralStatus::Rejected)
        ->and(ReferralReward::query()->sole()->status)->toBe(ReferralRewardStatus::Revoked)
        ->and(loggedFor($this->superAdmin, 'referral.rejected'))->toBeTrue();

    $this->post(route('super-admin.referrals.referrals.restore', $referral), ['current_password' => 'password'])->assertRedirect();
    expect($referral->fresh()->status)->toBe(ReferralStatus::Verified);
});

it('shows an invalid state change as a form error', function () {
    $referral = referralWithReward();

    $this->post(route('super-admin.referrals.referrals.restore', $referral), ['current_password' => 'password'])
        ->assertSessionHasErrors('referral');
});

it('approves and revokes rewards behind the password', function () {
    $referral = referralWithReward(manualApproval: true);
    $reward = ReferralReward::query()->sole();
    $workspace = $referral->referredWorkspace;

    $this->post(route('super-admin.referrals.rewards.approve', $reward), ['current_password' => 'wrong'])
        ->assertSessionHasErrorsIn('support', 'current_password');
    expect($reward->fresh()->status)->toBe(ReferralRewardStatus::AwaitingApproval);

    $this->post(route('super-admin.referrals.rewards.approve', $reward), ['current_password' => 'password'])->assertRedirect();
    expect($reward->fresh()->status)->toBe(ReferralRewardStatus::Granted)
        ->and($workspace->fresh()->topup_credits)->toBe(400);

    $this->post(route('super-admin.referrals.rewards.revoke', $reward), ['reason' => 'Duplicate account', 'current_password' => 'password'])->assertRedirect();
    expect($reward->fresh()->status)->toBe(ReferralRewardStatus::Revoked)
        ->and($workspace->fresh()->topup_credits)->toBe(0)
        ->and(loggedFor($this->superAdmin, 'referral_reward.approved'))->toBeTrue()
        ->and(loggedFor($this->superAdmin, 'referral_reward.revoked'))->toBeTrue();
});

it('grants a goodwill reward behind the password', function () {
    $user = referralUser();

    $this->post(route('super-admin.referrals.rewards.store'), [
        'user_id' => $user->id,
        'reward_type' => 'credits',
        'credits' => 250,
        'notes' => 'Sorry about the outage',
        'current_password' => 'wrong',
    ])->assertSessionHasErrors('current_password');

    $this->post(route('super-admin.referrals.rewards.store'), [
        'user_id' => $user->id,
        'reward_type' => 'credits',
        'credits' => 250,
        'notes' => 'Sorry about the outage',
        'current_password' => 'password',
    ])->assertRedirect();

    expect($user->currentWorkspace->fresh()->topup_credits)->toBe(250)
        ->and(loggedFor($this->superAdmin, 'referral_reward.manual_granted'))->toBeTrue();
});

it('edits a referral code', function () {
    $code = app(ReferralCodes::class)->forUser(referralUser());

    $this->put(route('super-admin.referrals.codes.update', $code), [
        'code' => 'partner-acme',
        'is_active' => '0',
        'rule_multiplier' => 2,
    ])->assertRedirect();

    expect($code->fresh()->code)->toBe('partner-acme')
        ->and($code->fresh()->is_active)->toBeFalse()
        ->and(loggedFor($this->superAdmin, 'referral_code.updated'))->toBeTrue();
});

it('blocks and unblocks a domain', function () {
    $this->post(route('super-admin.referrals.blocked-domains.store'), ['domain' => ' Spam.Example ', 'reason' => 'Throwaway'])->assertRedirect();

    $domain = ReferralBlockedDomain::query()->sole();
    expect($domain->domain)->toBe('spam.example');

    $this->post(route('super-admin.referrals.blocked-domains.store'), ['domain' => 'not a domain'])->assertSessionHasErrors('domain');

    $this->delete(route('super-admin.referrals.blocked-domains.destroy', $domain))->assertRedirect();
    expect(ReferralBlockedDomain::query()->count())->toBe(0)
        ->and(loggedFor($this->superAdmin, 'referral_domain.blocked'))->toBeTrue()
        ->and(loggedFor($this->superAdmin, 'referral_domain.unblocked'))->toBeTrue();
});
