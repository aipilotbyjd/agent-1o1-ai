<?php

namespace App\Http\Resources\Api\Internal\V1\Workflows;

use App\Models\Workflows\WorkflowVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowVersion
 */
class WorkflowVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'version' => $this->version,
            'graph' => $this->graph,
            'notes' => $this->notes,
            'published_by' => $this->published_by,
            'created_at' => $this->created_at,
        ];
    }
}
