<?php

namespace App\Http\Requests\SuperAdmin\Referrals;

use App\Http\Requests\Api\Internal\V1\Admin\Referrals\ReferralRewardRuleRequest;

/**
 * The API's reward-rule rules (including its consistency checks), fed from
 * an HTML form: condition fields left blank are dropped rather than sent as
 * nulls the API rules would reject.
 */
class RuleRequest extends ReferralRewardRuleRequest
{
    protected function prepareForValidation(): void
    {
        $conditions = $this->input('conditions');

        if (! is_array($conditions)) {
            $this->merge(['conditions' => null]);

            return;
        }

        $conditions = array_filter($conditions, fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        $this->merge(['conditions' => $conditions === [] ? null : $conditions]);
    }
}
