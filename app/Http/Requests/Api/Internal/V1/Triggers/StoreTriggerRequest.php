<?php

namespace App\Http\Requests\Api\Internal\V1\Triggers;

use App\Enums\Triggers\TriggerTargetType;
use App\Enums\Triggers\TriggerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTriggerRequest extends FormRequest
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
            'target_type' => ['required', Rule::enum(TriggerTargetType::class)],
            'target_id' => ['required', 'integer', 'min:1'],
            'type' => ['required', Rule::enum(TriggerType::class)],
            'preset_id' => ['nullable', 'integer', 'exists:trigger_presets,id'],
            'config' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'credential_id' => ['nullable', 'integer'],
            'signing_secret' => ['nullable', 'string'],
        ];
    }
}
