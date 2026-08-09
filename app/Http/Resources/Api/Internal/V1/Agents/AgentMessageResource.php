<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Models\Agents\AgentMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentMessage
 */
class AgentMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_session_id' => $this->agent_session_id,
            'role' => $this->role->value,
            'content' => $this->content,
            'usage' => $this->usage,
            'created_at' => $this->created_at,
        ];
    }
}
