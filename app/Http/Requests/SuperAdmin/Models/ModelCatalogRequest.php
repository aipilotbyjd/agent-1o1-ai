<?php

namespace App\Http\Requests\SuperAdmin\Models;

use App\Enums\Ai\ModelSpeed;
use App\Models\Ai\ModelCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A model catalog entry. The slug is what agents store, so it is set on
 * create and never changed afterwards. Strengths arrive as one per line.
 */
class ModelCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('strengths'))) {
            $this->merge([
                'strengths' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $this->input('strengths'))))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $entry = $this->route('model');
        $creating = ! $entry instanceof ModelCatalog;

        return [
            'slug' => $creating
                ? ['required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9.\-]*$/', Rule::unique('model_catalog', 'slug')]
                : ['prohibited'],
            'display_name' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'strengths' => ['nullable', 'array', 'max:6'],
            'strengths.*' => ['string', 'max:60'],
            'speed' => ['nullable', Rule::enum(ModelSpeed::class)],
            'cost_tier' => ['nullable', 'integer', 'min:1', 'max:5'],
            'context_window' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'vision' => ['required', 'boolean'],
            'tool_use' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'is_internal' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'retires_at' => ['nullable', 'date'],
            'replacement_slug' => [
                'nullable',
                'string',
                Rule::exists('model_catalog', 'slug'),
                Rule::notIn(array_filter([$creating ? $this->input('slug') : $entry->slug])),
            ],
        ];
    }
}
