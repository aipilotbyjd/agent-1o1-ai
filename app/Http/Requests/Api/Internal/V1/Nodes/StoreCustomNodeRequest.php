<?php

namespace App\Http\Requests\Api\Internal\V1\Nodes;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomNodeRequest extends FormRequest
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
            'category_id' => ['required', 'integer', 'exists:node_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
            'config_schema' => ['required', 'array'],
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
            'input_schema' => ['nullable', 'array'],
            'output_schema' => ['nullable', 'array'],
            'credential_type' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
