<?php

namespace App\Http\Resources\Api\Internal\V1\Runs;

use App\Models\Runs\Run;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Run
 */
class RunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'runnable_type' => $this->runnable_type,
            'runnable_id' => $this->runnable_id,
            'workflow_id' => $this->workflow_id,
            'workflow_version_id' => $this->workflow_version_id,
            'status' => $this->status->value,
            'trigger_type' => $this->trigger_type,
            'input' => $this->input,
            'output' => $this->output,
            'error' => $this->error,
            'node_runs' => NodeRunResource::collection($this->whenLoaded('nodeRuns')),
            'triggered_by' => $this->triggered_by,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'created_at' => $this->created_at,
        ];
    }
}
