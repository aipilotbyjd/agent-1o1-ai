<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\HasSearchTerm;
use Illuminate\Foundation\Http\FormRequest;

class IndexUsersRequest extends FormRequest
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
            'two_factor' => ['nullable', 'in:enabled,disabled'],
            'verified' => ['nullable', 'in:yes,no'],
        ];
    }
}
