<?php

namespace App\Http\Requests\SuperAdmin\Referrals;

use App\Http\Requests\Api\Internal\V1\Admin\Referrals\ReferralProgramRequest;

/**
 * The API's program rules, fed from an HTML form: unchecked plan boxes
 * arrive as an empty string (meaning "any plan"), and the slug may be left
 * blank on create to be generated from the name.
 */
class ProgramRequest extends ReferralProgramRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('slug') === null) {
            $this->request->remove('slug');
        }

        if (! is_array($this->input('referrer_eligible_plan_ids'))) {
            $this->merge(['referrer_eligible_plan_ids' => null]);
        }
    }
}
