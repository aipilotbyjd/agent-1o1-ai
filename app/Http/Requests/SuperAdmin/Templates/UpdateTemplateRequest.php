<?php

namespace App\Http\Requests\SuperAdmin\Templates;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gallery metadata only. A template's graph or agent config is built in the
 * product and copied in, never hand-edited here.
 */
class UpdateTemplateRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:32', 'regex:/^[#a-zA-Z0-9-]+$/'],
        ];
    }
}
