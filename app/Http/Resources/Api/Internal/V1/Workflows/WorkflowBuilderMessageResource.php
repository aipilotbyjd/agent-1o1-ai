<?php

namespace App\Http\Resources\Api\Internal\V1\Workflows;

use App\Models\Workflows\Builder\WorkflowBuilderMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowBuilderMessage
 */
class WorkflowBuilderMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'draft_version_id' => $this->draft_version_id,
            'role' => $this->role,
            'content' => $this->content,
            'actions' => $this->actions,
            'created_at' => $this->created_at,
        ];
    }
}
