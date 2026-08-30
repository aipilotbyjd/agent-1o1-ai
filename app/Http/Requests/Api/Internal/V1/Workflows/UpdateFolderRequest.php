<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use App\Models\Workflows\Folder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFolderRequest extends FormRequest
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
        /** @var Folder $folder */
        $folder = $this->route('folder');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'color' => ['sometimes', 'nullable', 'string', 'max:7'],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('folders', 'id')->where('type', $folder->type->value),
            ],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
