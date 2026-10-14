<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\SuperAdmin\SuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSuperAdminPasswordRequest extends FormRequest
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
        return [
            'current_password' => ['required', 'string', 'current_password:super_admin'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', SuperAdmin::passwordRule()],
        ];
    }
}
