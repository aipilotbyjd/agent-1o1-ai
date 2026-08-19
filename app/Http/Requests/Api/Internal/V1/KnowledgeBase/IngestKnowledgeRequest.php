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
            // Embedding happens inline, so the cap keeps one request from
            // turning into minutes of provider calls. Ingest a large corpus
            // as several documents rather than one.
            'text' => ['required', 'string', 'max:200000'],
            // Where this text came from — a filename, URL, or ticket id.
            // Returned with every search hit so an answer can be traced back.
            'source' => ['nullable', 'string', 'max:255'],
            'collection' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
