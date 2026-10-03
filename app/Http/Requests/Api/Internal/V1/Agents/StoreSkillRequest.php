<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Http\Requests\Api\Internal\V1\Agents\Concerns\ValidatesSkillFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreSkillRequest extends FormRequest
{
    use ValidatesSkillFields;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = $this->skillFieldRules();

        return [
            ...$rules,
            'name' => ['required', ...$rules['name']],
            'slug' => ['nullable', ...$rules['slug']],
            'instructions' => ['required', ...$rules['instructions']],
            'is_shared' => ['sometimes', ...$rules['is_shared']],
        ];
    }
}
