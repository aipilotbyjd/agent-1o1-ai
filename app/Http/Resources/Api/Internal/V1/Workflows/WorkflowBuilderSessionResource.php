<?php

namespace App\Http\Resources\Api\Internal\V1\Workflows;

use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowBuilderSession
 */
class WorkflowBuilderSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'user_id' => $this->user_id,
            'workflow_id' => $this->workflow_id,
            'title' => $this->title,
            'draft_graph' => $this->draft_graph,
            'draft_lock_version' => $this->draft_lock_version,
            'status' => $this->status,
            'last_activity_at' => $this->last_activity_at,
            'messages' => WorkflowBuilderMessageResource::collection($this->whenLoaded('messages')),
            'created_at' => $this->created_at,
        ];
    }
}
