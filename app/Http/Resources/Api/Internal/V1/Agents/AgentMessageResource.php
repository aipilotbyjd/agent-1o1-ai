<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Ai\Tools\InvokeAgentTool;
use App\Http\Resources\Api\Internal\V1\Artifacts\ArtifactResource;
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
            'attachments' => ArtifactResource::collection($this->whenLoaded('attachments')),
            'tool_calls' => $this->tool_calls,
            // Tool call id => the subagent task it started, for the chat's status cards.
            'subagent_task_ids' => (object) collect($this->tool_results ?? [])
                ->filter(fn (array $result): bool => ($result['name'] ?? null) === InvokeAgentTool::NAME)
                ->mapWithKeys(fn (array $result): array => [$result['id'] => json_decode((string) $result['result'], true)['task_id'] ?? null])
                ->filter()
                ->all(),
            'usage' => $this->usage,
            'created_at' => $this->created_at,
        ];
    }
}
