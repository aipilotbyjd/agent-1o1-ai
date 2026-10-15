<?php

namespace App\Http\Requests\SuperAdmin\Referrals;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rejecting a referral or revoking a reward claws back what was granted, so
 * it takes a reason and the super admin's password.
 */
class ReasonRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $errorBag = 'reason';

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
            'reason' => ['required', 'string', 'min:5', 'max:255'],
            'current_password' => ['required', 'string', 'current_password:super_admin'],
        ];
    }
}
