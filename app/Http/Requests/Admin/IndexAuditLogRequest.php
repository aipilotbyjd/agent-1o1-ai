<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IndexAuditLogRequest extends FormRequest
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
            'action' => ['nullable', 'string', 'max:100'],
            'actor' => ['nullable', 'in:panel,platform,system'],
            'admin' => ['nullable', 'uuid'],
            'subject' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    /**
     * The action filter as a prefix match, so `admin.login` finds every
     * sign-in event.
     */
    public function actionPrefix(): ?string
    {
        $action = trim((string) $this->validated('action'));

        return $action === '' ? null : addcslashes($action, '%_\\').'%';
    }
}
