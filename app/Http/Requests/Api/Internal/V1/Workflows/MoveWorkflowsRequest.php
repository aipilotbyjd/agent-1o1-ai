<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;

class MoveWorkflowsRequest extends FormRequest
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
            'workflow_ids' => ['required', 'array', 'min:1'],
            'workflow_ids.*' => ['uuid'],
            'folder_id' => ['nullable', 'uuid', 'exists:folders,id'],
        ];
    }
}
