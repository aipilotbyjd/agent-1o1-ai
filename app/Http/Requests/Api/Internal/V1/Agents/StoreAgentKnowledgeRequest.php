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
            'source_type' => ['nullable', Rule::in(['text', 'url', 'file'])],
            'source_url' => ['nullable', 'url', 'max:2048', 'required_if:source_type,url'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
