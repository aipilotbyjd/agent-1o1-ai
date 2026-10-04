<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentKnowledgeRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            // Every entry is injected verbatim into the agent's system prompt
            // (SkillInjector), so the cap is about prompt budget, not storage.
            'content' => ['required', 'string', 'max:50000'],
            // `sometimes` without `nullable`: these columns are NOT NULL, so an
            // explicit null must be rejected rather than override the default.
            'source_type' => ['sometimes', Rule::in(['text', 'url', 'file'])],
            'source_url' => ['nullable', 'url', 'max:2048', 'required_if:source_type,url'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array', 'max:50'],
        ];
    }
}
