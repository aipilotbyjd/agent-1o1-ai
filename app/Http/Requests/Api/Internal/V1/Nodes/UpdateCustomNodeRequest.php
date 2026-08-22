<?php

namespace App\Http\Requests\Api\Internal\V1\Nodes;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomNodeRequest extends FormRequest
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
            'category_id' => ['sometimes', 'integer', 'exists:node_categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:50'],
            'color' => ['sometimes', 'nullable', 'string', 'max:20'],
            'config_schema' => ['sometimes', 'array'],
            /*
             * What the node does when the engine reaches it. Nullable: a row
             * without one is a catalogue entry the engine refuses to run
             * (`CustomNode::isExecutable()`). Only `http` exists today, so
             * `kind` is validated against exactly that rather than left open.
             */
            'implementation' => ['sometimes', 'nullable', 'array'],
            'implementation.kind' => ['required_with:implementation', 'string', 'in:http'],
            'implementation.method' => ['required_with:implementation', 'string', 'in:GET,POST,PUT,PATCH,DELETE'],
            'implementation.url' => ['required_with:implementation', 'string', 'max:2048'],
            'implementation.headers' => ['nullable', 'array'],
            'implementation.query' => ['nullable', 'array'],
            'implementation.body' => ['nullable', 'array'],
            'implementation.timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:300'],
            'input_schema' => ['sometimes', 'nullable', 'array'],
            'output_schema' => ['sometimes', 'nullable', 'array'],
            'credential_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
