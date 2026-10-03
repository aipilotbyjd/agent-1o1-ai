<?php

namespace App\Http\Requests\Api\Internal\V1\Triggers;

use App\Enums\Agents\AutonomyMode;
use App\Rules\SafeOutboundUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // For an agent target: the mode its triggered turns run under, overriding the agent's own.
            'config.autonomy_mode' => ['nullable', Rule::enum(AutonomyMode::class)],
            'config.test_mode' => ['nullable', 'boolean'],
            'config.url' => ['nullable', 'string', new SafeOutboundUrl],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
