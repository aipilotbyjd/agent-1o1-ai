<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSkillScriptRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'language' => ['sometimes', 'string', Rule::in(['python', 'javascript', 'typescript', 'bash'])],
            'code' => ['sometimes', 'string'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
