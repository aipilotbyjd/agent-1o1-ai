<?php

namespace App\Http\Requests\Api\Internal\V1\Connectors;

use Illuminate\Foundation\Http\FormRequest;

class StoreConnectorCredentialRequest extends FormRequest
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
            'connector_id' => ['required', 'integer', 'exists:connectors,id'],
            'name' => ['required', 'string', 'max:255'],
            'data' => ['required', 'array'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
