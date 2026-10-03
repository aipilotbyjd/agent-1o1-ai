<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Models\Agents\SkillScript;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSkillRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'instructions' => ['required', 'string'],
            'is_shared' => ['nullable', 'boolean'],
            'references' => ['sometimes', 'array', 'max:50'],
            'references.*.title' => ['required', 'string', 'max:255'],
            'references.*.content' => ['required', 'string'],
            'scripts' => ['sometimes', 'array', 'max:20'],
            'scripts.*.name' => ['required', 'string', 'max:255'],
            'scripts.*.description' => ['nullable', 'string'],
            'scripts.*.language' => ['required', 'string', Rule::in(SkillScript::LANGUAGES)],
            'scripts.*.code' => ['required', 'string'],
        ];
    }
}
