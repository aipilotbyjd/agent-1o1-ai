<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentRequest extends FormRequest
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
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash'],
            'description' => ['sometimes', 'nullable', 'string'],
            'folder_id' => ['sometimes', 'nullable', 'integer'],
            'instructions' => ['sometimes', 'string'],
            'provider' => ['sometimes', 'string', 'max:255'],
            'model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'model_catalog_id' => ['sometimes', 'nullable', 'integer', 'exists:model_catalog,id'],
            'temperature' => ['sometimes', 'nullable', 'numeric', 'between:0,1'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
