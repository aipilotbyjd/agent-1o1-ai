<?php

namespace App\Models\Agents;

use App\Enums\Agents\AgentMessageRole;
use Database\Factories\Agents\AgentMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `usage` (token/credit accounting, same shape as `node_runs.usage`) is
 * engine-managed — not in `#[Fillable]`, written via `forceFill()` after
 * create(), mirroring `NodeRun`'s own convention.
 */
#[Fillable(['agent_session_id', 'role', 'content', 'tool_calls', 'tool_call_id'])]
class AgentMessage extends Model
{
    /** @use HasFactory<AgentMessageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => AgentMessageRole::class,
            'tool_calls' => 'array',
            'usage' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'agent_session_id');
    }
}
