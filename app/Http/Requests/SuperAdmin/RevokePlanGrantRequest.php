<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class RevokePlanGrantRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $errorBag = 'revokePlanGrant';

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
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'current_password' => ['required', 'string', 'current_password:super_admin'],
        ];
    }
}
