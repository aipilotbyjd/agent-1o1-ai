<?php

namespace App\Http\Requests\Api\Internal\V1\Notifications;

use App\Enums\Workspaces\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationChannelRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:100'],
            'config' => ['sometimes', 'array'],
            'config.url' => ['required_with:config', 'url'],
            'config.headers' => ['sometimes', 'array'],
            'config.headers.*' => ['string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
