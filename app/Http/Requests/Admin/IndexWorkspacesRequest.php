<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\HasSearchTerm;
use Illuminate\Foundation\Http\FormRequest;

class IndexWorkspacesRequest extends FormRequest
{
    use HasSearchTerm;

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
            'q' => ['nullable', 'string', 'max:255'],
            'deleted' => ['nullable', 'in:with,only'],
        ];
    }
}
