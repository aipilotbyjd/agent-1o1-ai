<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Gives a workspace a plan for a fixed number of days, as a `manual`
 * plan grant. There is no open-ended option: every manual grant expires.
 */
class GrantWorkspacePlanTimeRequest extends FormRequest
{
    public const int MAX_DAYS = 3650;

    /**
     * @var string
     */
    protected $errorBag = 'grantPlanTime';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'uuid', Rule::exists('plans', 'id')->where('is_active', true)],
            'days' => ['required', 'integer', 'min:1', 'max:'.self::MAX_DAYS],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'current_password' => ['required', 'string', 'current_password:super_admin'],
        ];
    }
}
