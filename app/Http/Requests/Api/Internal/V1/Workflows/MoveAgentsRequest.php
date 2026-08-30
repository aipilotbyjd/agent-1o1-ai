<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;

class MoveAgentsRequest extends FormRequest
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
            'agent_ids' => ['required', 'array', 'min:1'],
            'agent_ids.*' => ['integer'],
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
        ];
    }
}
