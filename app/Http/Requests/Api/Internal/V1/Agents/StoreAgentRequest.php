<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Models\Agents\Agent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'icon' => ['nullable', Rule::in(Agent::ICONS)],
            'color' => ['nullable', Rule::in(Agent::COLORS)],
            'folder_id' => ['nullable', 'uuid'],
            'instructions' => ['required', 'string'],
            'provider' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'model_catalog_id' => ['nullable', 'uuid', 'exists:model_catalog,id'],
            'temperature' => ['nullable', 'numeric', 'between:0,1'],
            'settings' => ['nullable', 'array'],
            'allow_self_updates' => ['sometimes', 'boolean'],
        ];
    }
}
