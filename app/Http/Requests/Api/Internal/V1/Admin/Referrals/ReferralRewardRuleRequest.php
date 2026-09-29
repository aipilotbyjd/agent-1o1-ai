<?php

namespace App\Http\Requests\Api\Internal\V1\Admin\Referrals;

use App\Enums\Billing\BillingInterval;
use App\Enums\Referrals\AlreadyOnPlanBehavior;
use App\Enums\Referrals\ReferralPaymentSource;
use App\Enums\Referrals\ReferralRecipient;
use App\Enums\Referrals\ReferralRewardType;
use App\Enums\Referrals\ReferralTrigger;
use App\Models\Referrals\ReferralRewardRule;
use BackedEnum;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a rule create (POST) or partial update (PATCH), then checks the
 * rule *as it would be saved* (input merged over the existing rule) makes
 * sense as a whole — a plan-time reward needs a plan and a length, a trial
 * extension can only go to the referred user, and so on — so no rule that
 * can never pay out can be saved.
 */
class ReferralRewardRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $presence = $this->existingRule() !== null ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'trigger' => [$presence, Rule::in(ReferralTrigger::ruleValues())],
            'recipient' => [$presence, Rule::enum(ReferralRecipient::class)],
            'reward_type' => [$presence, Rule::enum(ReferralRewardType::class)],
            'credits_amount' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000000'],
            'plan_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('plans', 'id')],
            'duration_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
            'amount_cents' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000000'],
            'amount_percent_of_plan' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'trial_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:365'],
            'milestone_count' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000000'],
            'hold_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'if_already_on_plan' => ['sometimes', Rule::enum(AlreadyOnPlanBehavior::class)],
            'fallback_credits' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000'],
            'max_per_recipient' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'conditions' => ['sometimes', 'nullable', 'array:min_payment_cents,plan_ids,billing_intervals,payment_sources,nth_payment_min,nth_payment_max'],
            'conditions.min_payment_cents' => ['sometimes', 'integer', 'min:0'],
            'conditions.plan_ids' => ['sometimes', 'array'],
            'conditions.plan_ids.*' => ['uuid', Rule::exists('plans', 'id')],
            'conditions.billing_intervals' => ['sometimes', 'array'],
            'conditions.billing_intervals.*' => [Rule::enum(BillingInterval::class)],
            'conditions.payment_sources' => ['sometimes', 'array'],
            'conditions.payment_sources.*' => [Rule::enum(ReferralPaymentSource::class)],
            'conditions.nth_payment_min' => ['sometimes', 'integer', 'min:1'],
            'conditions.nth_payment_max' => ['sometimes', 'integer', 'min:1'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $rule = [...($this->existingRule()?->attributesToArray() ?? []), ...$this->validated()];
                $type = ReferralRewardType::tryFrom((string) $this->enumValue($rule['reward_type'] ?? null));
                $trigger = ReferralTrigger::tryFrom((string) $this->enumValue($rule['trigger'] ?? null));
                $recipient = ReferralRecipient::tryFrom((string) $this->enumValue($rule['recipient'] ?? null));

                $missing = fn (string $key): bool => ($rule[$key] ?? null) === null;

                match ($type) {
                    ReferralRewardType::Credits => $missing('credits_amount')
                        && $validator->errors()->add('credits_amount', 'A credits reward needs an amount.'),
                    ReferralRewardType::PlanTime => ($missing('plan_id') || $missing('duration_days'))
                        && $validator->errors()->add('plan_id', 'A plan-time reward needs a plan and a number of days.'),
                    ReferralRewardType::StripeBalanceCredit => $missing('amount_cents') && ($missing('amount_percent_of_plan') || $missing('plan_id'))
                        && $validator->errors()->add('amount_cents', 'An invoice credit needs a fixed amount, or a percentage of a plan\'s monthly price.'),
                    ReferralRewardType::TrialExtension => $missing('trial_days')
                        && $validator->errors()->add('trial_days', 'A trial extension needs a number of days.'),
                    default => null,
                };

                if ($type === ReferralRewardType::TrialExtension && $recipient !== ReferralRecipient::Referee) {
                    $validator->errors()->add('recipient', 'Only the referred user can receive a trial extension.');
                }

                if ($trigger === ReferralTrigger::Milestone) {
                    if ($missing('milestone_count')) {
                        $validator->errors()->add('milestone_count', 'A milestone rule needs the number of converted referrals it pays out at.');
                    }

                    if ($recipient !== ReferralRecipient::Referrer) {
                        $validator->errors()->add('recipient', 'Milestone rewards go to the referrer.');
                    }
                }
            },
        ];
    }

    private function existingRule(): ?ReferralRewardRule
    {
        $rule = $this->route('rule');

        return $rule instanceof ReferralRewardRule ? $rule : null;
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
