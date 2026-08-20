<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Enums\Agents\AgentSessionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentSessionRequest extends FormRequest
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
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(AgentSessionStatus::class)],
        ];
    }
}
