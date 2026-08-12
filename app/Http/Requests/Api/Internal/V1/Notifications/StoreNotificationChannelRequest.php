<?php

namespace App\Http\Requests\Api\Internal\V1\Notifications;

use App\Enums\Workspaces\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationChannelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::NotificationChannelManage->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['discord', 'slack', 'webhook'])],
            'name' => ['required', 'string', 'max:100'],
            'config' => ['required', 'array'],
            'config.url' => ['required', 'url'],
            'config.headers' => ['sometimes', 'array'],
            'config.headers.*' => ['string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
