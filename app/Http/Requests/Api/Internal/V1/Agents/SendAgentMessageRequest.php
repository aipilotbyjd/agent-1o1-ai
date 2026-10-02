<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Models\Agents\Agent;
use App\Models\Agents\Skill;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class SendAgentMessageRequest extends FormRequest
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
            'message' => ['required', 'string'],
            // A skill picked for this message (`/` in the chat); must be
            // attached to the agent — see `chosenSkill()`.
            'skill_id' => ['nullable', 'uuid'],
            // Sent as multipart `attachments[]`. Each file is stored as an
            // artifact on the new message and handed to the model with it.
            'attachments' => ['nullable', 'array', 'max:'.config('artifacts.message_attachments.max_files')],
            'attachments.*' => [
                'file',
                'max:'.config('artifacts.max_upload_kilobytes'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    // Detected from the file's own bytes, not the client's
                    // Content-Type header — the same value the artifact is
                    // stored and later sent to the provider as.
                    $allowed = self::allowedAttachmentMimeTypes();

                    if (! in_array($value->getMimeType(), $allowed, true)) {
                        $fail('The :attribute must be an image (JPEG, PNG, GIF, WebP), a PDF, or a plain-text file.');
                    }
                },
            ],
        ];
    }

    /**
     * The picked skill, refused unless it is attached to `$agent`: a member
     * may only point the agent at skills it was given.
     *
     * @throws ValidationException
     */
    public function chosenSkill(Agent $agent): ?Skill
    {
        $skillId = $this->validated('skill_id');

        if ($skillId === null) {
            return null;
        }

        return $agent->skills()->whereKey($skillId)->first()
            ?? throw ValidationException::withMessages(['skill_id' => 'That skill is not attached to this agent.']);
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function attachmentFiles(): array
    {
        return $this->file('attachments', []);
    }

    /**
     * @return array<int, string>
     */
    private static function allowedAttachmentMimeTypes(): array
    {
        $config = config('artifacts.message_attachments');

        return [...$config['image_mime_types'], ...$config['document_mime_types'], ...$config['text_mime_types']];
    }
}
