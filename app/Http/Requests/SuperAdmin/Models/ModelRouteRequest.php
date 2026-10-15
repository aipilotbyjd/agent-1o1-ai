<?php

namespace App\Http\Requests\SuperAdmin\Models;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An execution backend for a catalog entry. The provider must be one
 * defined in `config/ai.php`; whether it has a key is shown, not enforced,
 * so a route can be prepared before the key is deployed.
 */
class ModelRouteRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $errorBag = 'route';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $updating = $this->route('route') !== null;

        return [
            'execution_provider' => $updating ? ['prohibited'] : ['required', 'string', Rule::in(array_keys((array) config('ai.providers')))],
            'execution_model_id' => $updating ? ['prohibited'] : ['required', 'string', 'max:255'],
            'priority' => ['required', 'integer', 'min:0', 'max:1000'],
            'is_enabled' => ['required', 'boolean'],
        ];
    }
}
