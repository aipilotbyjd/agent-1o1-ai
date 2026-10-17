<?php

namespace App\Http\Requests\SuperAdmin\Templates;

use App\Services\SuperAdmin\SuperAdminTemplates;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `template` is `<kind>:<id>`, the value of one option in the "add a
 * template" picker.
 */
class AddCollectionItemRequest extends FormRequest
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
        $kinds = implode('|', array_keys(SuperAdminTemplates::KINDS));

        return [
            'template' => ['required', 'string', 'regex:/^('.$kinds.'):[0-9a-f-]{36}$/i'],
        ];
    }

    /**
     * @return array{0: string, 1: string} [kind, id]
     */
    public function template(): array
    {
        return explode(':', (string) $this->validated('template'), 2);
    }
}
