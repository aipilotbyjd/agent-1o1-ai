<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Models\Agents\Agent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'icon' => ['sometimes', 'nullable', Rule::in(Agent::ICONS)],
            'color' => ['sometimes', 'nullable', Rule::in(Agent::COLORS)],
            'folder_id' => ['sometimes', 'nullable', 'uuid'],
            'instructions' => ['sometimes', 'string'],
            'provider' => ['sometimes', 'string', 'max:255'],
            'model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'model_catalog_id' => ['sometimes', 'nullable', 'uuid', 'exists:model_catalog,id'],
            'temperature' => ['sometimes', 'nullable', 'numeric', 'between:0,1'],
            'settings' => ['sometimes', 'nullable', 'array'],
            'allow_self_updates' => ['sometimes', 'boolean'],
        ];
    }
}
