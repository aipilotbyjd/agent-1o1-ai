<?php

namespace App\Http\Resources\Api\Internal\V1\Assistant;

use App\Models\Assistant\AssistantMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssistantMessage
 */
class AssistantMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assistant_session_id' => $this->assistant_session_id,
            'role' => $this->role->value,
            'content' => $this->content,
            'tool_calls' => collect($this->tool_calls ?? [])->map(fn (array $call): array => [
                'id' => $call['id'] ?? null,
                'name' => $call['name'] ?? null,
            ])->values(),
            'tool_call_id' => $this->tool_call_id,
            'attachments' => $this->attachments ?? [],
            'compacted' => $this->compacted_into_id !== null,
            'feedback' => $this->whenLoaded('feedback', fn () => $this->feedback === null ? null : [
                'rating' => $this->feedback->rating->value,
                'comment' => $this->feedback->comment,
                'status' => $this->feedback->status->value,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
