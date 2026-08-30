<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentRequest extends FormRequest
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
            'folder_id' => ['nullable', 'integer'],
            'instructions' => ['required', 'string'],
            'provider' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'model_catalog_id' => ['nullable', 'integer', 'exists:model_catalog,id'],
            'temperature' => ['nullable', 'numeric', 'between:0,1'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
