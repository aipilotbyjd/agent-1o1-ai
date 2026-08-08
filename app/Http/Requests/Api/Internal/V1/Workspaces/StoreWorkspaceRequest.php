<?php

namespace App\Http\Requests\Api\Internal\V1\Workspaces;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceRequest extends FormRequest
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
        ];
    }
}
