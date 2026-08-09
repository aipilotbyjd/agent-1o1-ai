<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentToolBindingRequest extends FormRequest
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
            'node_type' => ['required', 'string', 'max:255'],
            'config' => ['nullable', 'array'],
            'exposed_fields' => ['nullable', 'array'],
            'exposed_fields.*' => ['string'],
        ];
    }
}
