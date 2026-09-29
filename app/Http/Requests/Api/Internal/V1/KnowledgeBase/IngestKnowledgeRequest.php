<?php

namespace App\Http\Requests\Api\Internal\V1\KnowledgeBase;

use Illuminate\Foundation\Http\FormRequest;

class IngestKnowledgeRequest extends FormRequest
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
            // Exactly one of text/file — the ingested content either way.
            // Embedding happens inline, so the size caps keep one request
            // from turning into minutes of provider calls. Ingest a large
            // corpus as several documents rather than one.
            'text' => ['required_without:file', 'prohibits:file', 'nullable', 'string', 'max:200000'],
            'file' => [
                'required_without:text',
                'nullable',
                'file',
                'max:'.config('knowledge_base.max_upload_kilobytes'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $extension = strtolower((string) $value->getClientOriginalExtension());
                    $allowed = config('knowledge_base.allowed_extensions');

                    if (! in_array($extension, $allowed, true)) {
                        $fail('The file must be one of: '.implode(', ', $allowed).'. '
                            .'PDF and Office formats are not supported — there is no text-extraction '
                            .'library in this project; convert to plain text/Markdown first.');
                    }
                },
            ],
            // Where this text came from — a filename, URL, or ticket id.
            // Returned with every search hit so an answer can be traced
            // back. Defaults to the file's own name when ingesting a file.
            'source' => ['nullable', 'string', 'max:255'],
            'collection' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
