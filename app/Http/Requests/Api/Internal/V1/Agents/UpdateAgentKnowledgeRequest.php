<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentKnowledgeRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string', 'max:50000'],
            'source_type' => ['sometimes', Rule::in(['text', 'url', 'file'])],
            // A URL entry must keep a URL: required when the type is (or is
            // being set to) `url` and the entry has none stored yet.
            'source_url' => [
                'nullable',
                'url',
                'max:2048',
                Rule::requiredIf(fn (): bool => ($this->input('source_type') ?? $this->route('knowledge')?->source_type) === 'url'
                    && ! $this->has('source_url')
                    && blank($this->route('knowledge')?->source_url)),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array', 'max:50'],
        ];
    }
}
