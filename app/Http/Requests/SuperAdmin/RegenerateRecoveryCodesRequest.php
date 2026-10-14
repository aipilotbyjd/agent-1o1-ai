<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class RegenerateRecoveryCodesRequest extends FormRequest
{
    /**
     * Kept apart from the change-password form on the same page.
     *
     * @var string
     */
    protected $errorBag = 'recoveryCodes';

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
            'current_password' => ['required', 'string', 'current_password:super_admin'],
        ];
    }
}
