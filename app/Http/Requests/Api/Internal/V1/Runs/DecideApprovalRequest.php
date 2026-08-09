<?php

namespace App\Http\Requests\Api\Internal\V1\Runs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideApprovalRequest extends FormRequest
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
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
