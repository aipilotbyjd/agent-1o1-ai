<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Support actions change a customer's account, so the super admin
 * re-enters their own password first.
 */
class ConfirmSupportActionRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $errorBag = 'support';

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
