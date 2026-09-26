<?php

namespace App\Models\Agents;

use App\Enums\Agents\SubagentTaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One piece of work a conversation handed to a subagent through
 * `InvokeAgentTool`. It runs in the background (`RunSubagentTaskJob`) in its
 * own conversation (`session_id`), and the parent picks up the result with
 * `WaitForSubagentsTool`, which stamps `collected_at` so each result is
 * reported once.
 */
#[Fillable(['workspace_id', 'parent_session_id', 'agent_id', 'session_id', 'task'])]
class SubagentTask extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubagentTaskStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'collected_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function parentSession(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'parent_session_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'session_id');
    }
}
