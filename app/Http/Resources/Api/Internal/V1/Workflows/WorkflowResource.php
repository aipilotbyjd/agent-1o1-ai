<?php

namespace App\Http\Resources\Api\Internal\V1\Workflows;

use App\Models\Workflows\Workflow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Workflow
 */
class WorkflowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'folder_id' => $this->folder_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'current_version_id' => $this->current_version_id,
            'has_unpublished_changes' => $this->has_unpublished_changes,
            'is_published' => $this->isPublished(),
            'nodes' => WorkflowNodeResource::collection($this->whenLoaded('nodes')),
            'edges' => WorkflowEdgeResource::collection($this->whenLoaded('edges')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
