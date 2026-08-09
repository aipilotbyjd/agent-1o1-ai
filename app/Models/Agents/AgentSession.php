<?php

namespace App\Models\Agents;

use App\Enums\Agents\AgentSessionStatus;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Agents\AgentSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A conversation thread with an `Agent` — a `runnable` itself
 * (`runs.runnable_type = AgentSession::class`), one `Run` per turn, per
 * docs/WORKFLOWS_PLAN.md's `runs` table note on why agent invocations and
 * workflow executions share one table rather than two.
 */
#[Fillable(['workspace_id', 'agent_id', 'user_id', 'title'])]
class AgentSession extends Model
{
    /** @use HasFactory<AgentSessionFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AgentSessionStatus::class,
            'last_activity_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AgentMessage::class);
    }

    public function runs(): MorphMany
    {
        return $this->morphMany(Run::class, 'runnable');
    }
}
