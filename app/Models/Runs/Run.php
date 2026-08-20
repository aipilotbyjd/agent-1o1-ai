<?php

namespace App\Models\Runs;

use App\Enums\RunStatus;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowVersion;
use App\Models\Workspaces\Workspace;
use Database\Factories\Runs\RunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A single execution of either a `Workflow` (runnable_type = Workflow::class)
 * or an `AgentSession` (runnable_type = AgentSession::class, added in
 * docs/AGENTS_PLAN.md) — see docs/WORKFLOWS_PLAN.md's `runs` table section for
 * why this stays one shared, polymorphic table rather than two.
 */
#[Fillable([
    'workspace_id', 'runnable_type', 'runnable_id', 'workflow_id', 'workflow_version_id',
    'parent_run_id', 'retried_from_run_id', 'parent_node_id', 'loop_index', 'environment_id', 'trigger_type',
    'input', 'triggered_by',
])]
class Run extends Model
{
    /** @use HasFactory<RunFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'trigger_type' => 'manual',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'input' => 'array',
            'output' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function runnable(): MorphTo
    {
        return $this->morphTo();
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class);
    }

    public function parentRun(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_run_id');
    }

    public function childRuns(): HasMany
    {
        return $this->hasMany(self::class, 'parent_run_id');
    }

    /**
     * The run this one was retried from, if any — see `RetryRunAction`.
     */
    public function retriedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retried_from_run_id');
    }

    public function retries(): HasMany
    {
        return $this->hasMany(self::class, 'retried_from_run_id');
    }

    public function parentNode(): BelongsTo
    {
        return $this->belongsTo(NodeRun::class, 'parent_node_id');
    }

    public function nodeRuns(): HasMany
    {
        return $this->hasMany(NodeRun::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
