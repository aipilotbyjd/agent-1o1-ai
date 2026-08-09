<?php

namespace App\Http\Resources\Api\Internal\V1\Triggers;

use App\Models\Triggers\Trigger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Trigger
 */
class TriggerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'type' => $this->type->value,
            'preset_id' => $this->preset_id,
            'config' => $this->config,
            'token' => $this->when($this->type->usesToken(), $this->token),
            'is_active' => $this->is_active,
            'consecutive_failure_count' => $this->consecutive_failure_count,
            'last_run_at' => $this->last_run_at,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
