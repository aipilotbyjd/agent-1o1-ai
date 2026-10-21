<?php

namespace App\Http\Requests\SuperAdmin;

use App\Enums\RunStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRunsRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(RunStatus::class)],
            'workspace' => ['nullable', 'uuid'],
            'trigger_type' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'include_tests' => ['nullable', 'boolean'],
        ];
    }
}
