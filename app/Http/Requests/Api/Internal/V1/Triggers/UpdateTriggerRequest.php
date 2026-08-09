<?php

namespace App\Http\Requests\Api\Internal\V1\Triggers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTriggerRequest extends FormRequest
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
            'config' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
