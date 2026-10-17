<?php

namespace App\Http\Requests\SuperAdmin;

use App\Enums\Billing\SubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSubscriptionsRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(SubscriptionStatus::class)],
            'plan' => ['nullable', 'uuid'],
            'trial_ending' => ['nullable', 'boolean'],
            'canceling' => ['nullable', 'boolean'],
        ];
    }
}
