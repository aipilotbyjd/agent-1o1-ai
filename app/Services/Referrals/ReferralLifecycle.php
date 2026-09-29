<?php

namespace App\Services\Referrals;

use App\Actions\Referrals\RevokeReferralRewardAction;
use App\Enums\Referrals\ReferralRewardStatus;
use App\Enums\Referrals\ReferralStatus;
use App\Enums\Referrals\ReferralTrigger;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralPayment;
use App\Models\Referrals\ReferralReward;
use Illuminate\Support\Facades\DB;

/**
 * Moves a referral through its stages and fires the matching trigger on
 * the rule engine. Each transition is one-way and guarded, so every hook
 * (email verified, run completed, invoice paid) can call it as often as it
 * likes.
 */
class ReferralLifecycle
{
    public function __construct(
        private readonly ReferralRuleEngine $engine,
        private readonly PaymentFingerprintChecker $fingerprints,
        private readonly RevokeReferralRewardAction $revoke,
    ) {}

    public function markVerified(Referral $referral): void
    {
        if ($referral->isRejected() || $referral->hasReached(ReferralStatus::Verified)) {
            return;
        }

        $referral->update(['status' => ReferralStatus::Verified, 'verified_at' => now()]);

        $this->engine->fire($referral, ReferralTrigger::SignupVerified);
    }

    /**
     * A program that requires a verified email won't count usage from an
     * account that never verified one.
     */
    public function markActivated(Referral $referral): void
    {
        if ($referral->isRejected() || $referral->hasReached(ReferralStatus::Activated)) {
            return;
        }

        if ($referral->status === ReferralStatus::Pending && $referral->program?->require_verified_email) {
            return;
        }

        $referral->update(['status' => ReferralStatus::Activated, 'activated_at' => now()]);

        $this->engine->fire($referral, ReferralTrigger::Activated);
    }

    /**
     * Records a paid charge and fires `first_payment` (converting the
     * referral) or `repeat_payment`. A payment reference already on record
     * is ignored, so a redelivered webhook is harmless.
     */
    public function recordPayment(Referral $referral, ReferralPaymentData $payment): void
    {
        if ($referral->isRejected() || $payment->amountCents <= 0) {
            return;
        }

        $recorded = DB::transaction(function () use ($referral, $payment): ?ReferralPayment {
            Referral::query()->whereKey($referral->id)->lockForUpdate()->first();

            if (ReferralPayment::query()->where('reference', $payment->reference)->exists()) {
                return null;
            }

            return $referral->payments()->create([
                'reference' => $payment->reference,
                'payment_intent_id' => $payment->paymentIntentId,
                'source' => $payment->source,
                'amount_cents' => $payment->amountCents,
                'currency' => $payment->currency,
                'plan_id' => $payment->planId,
                'billing_interval' => $payment->interval,
                'sequence' => $referral->payments()->count() + 1,
            ]);
        });

        if ($recorded === null) {
            return;
        }

        $context = new TriggerContext(
            paymentCents: $payment->amountCents,
            planId: $payment->planId,
            interval: $payment->interval,
            paymentSource: $payment->source,
            paymentSequence: $recorded->sequence,
            paymentReference: $recorded->reference,
        );

        if ($recorded->sequence > 1) {
            $this->engine->fire($referral, ReferralTrigger::RepeatPayment, $context);

            return;
        }

        if ($referral->program?->fraudCheckEnabled('card_fingerprint') && $this->fingerprints->sharesCardWithReferrer($referral)) {
            $this->reject($referral, 'The referred account pays with a card the referrer also uses.');

            return;
        }

        $referral->update(['status' => ReferralStatus::Converted, 'converted_at' => now()]);

        $this->engine->fire($referral, ReferralTrigger::FirstPayment, $context);
        $this->engine->evaluateMilestones($referral);
    }

    /**
     * A refund or dispute withdraws what that payment earned. A partial
     * refund only counts when the program says so.
     */
    public function recordRefund(string $paymentIntentId, bool $fullyRefunded, string $reason): void
    {
        $payments = ReferralPayment::query()
            ->where('payment_intent_id', $paymentIntentId)
            ->whereNull('refunded_at')
            ->with('referral.program')
            ->get();

        foreach ($payments as $payment) {
            if (! $fullyRefunded && ! ($payment->referral?->program?->revoke_on_partial_refund ?? false)) {
                continue;
            }

            $payment->update(['refunded_at' => now()]);

            ReferralReward::query()
                ->where('payment_reference', $payment->reference)
                ->where('status', '!=', ReferralRewardStatus::Revoked)
                ->get()
                ->each(fn (ReferralReward $reward) => $this->revoke->execute($reward, $reason));
        }
    }

    /**
     * Rejecting a referral (fraud, or an admin's call) withdraws every
     * reward it produced, granted or not.
     */
    public function reject(Referral $referral, string $reason): void
    {
        $referral->update([
            'status' => ReferralStatus::Rejected,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $referral->rewards()
            ->where('status', '!=', ReferralRewardStatus::Revoked)
            ->get()
            ->each(fn (ReferralReward $reward) => $this->revoke->execute($reward, "Referral rejected: {$reason}"));
    }

    /**
     * Undoes a rejection, putting the referral back at the furthest stage
     * it had reached. Rewards revoked by the rejection stay revoked; an
     * admin re-grants any that should stand from the rewards API.
     */
    public function restore(Referral $referral): void
    {
        if (! $referral->isRejected()) {
            return;
        }

        $status = match (true) {
            $referral->converted_at !== null => ReferralStatus::Converted,
            $referral->activated_at !== null => ReferralStatus::Activated,
            $referral->verified_at !== null => ReferralStatus::Verified,
            default => ReferralStatus::Pending,
        };

        $referral->update([
            'status' => $status,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);
    }
}
