<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use App\Enums\Workflows\BuilderMessageMode;
use App\Http\Requests\Concerns\ValidatesMessageAttachments;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowBuilderMessageRequest extends FormRequest
{
    use ValidatesMessageAttachments;

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
            'message' => ['required', 'string', 'max:10000'],
            // `ask` answers without editing the draft. Defaults to `build`.
            'mode' => ['nullable', Rule::enum(BuilderMessageMode::class)],
            // Sent as multipart `attachments[]`. Each file is stored as an
            // artifact on the new message and handed to the model with it.
            // No PDFs: the builder's model routes only take images.
            ...$this->attachmentRules(documents: false),
        ];
    }

    public function mode(): BuilderMessageMode
    {
        return BuilderMessageMode::tryFrom((string) $this->validated('mode')) ?? BuilderMessageMode::Build;
    }
}
