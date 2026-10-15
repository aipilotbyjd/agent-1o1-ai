<?php

namespace App\Http\Requests\SuperAdmin;

use App\Enums\Billing\PlanGrantSource;
use App\Enums\Billing\PlanGrantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPlanGrantsRequest extends FormRequest
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
            'source' => ['nullable', Rule::enum(PlanGrantSource::class)],
            'status' => ['nullable', Rule::enum(PlanGrantStatus::class)],
            'workspace' => ['nullable', 'uuid'],
        ];
    }
}
