<?php

namespace App\Models\Runs;

use App\Enums\Billing\CreditTransactionType;
use App\Enums\RunStatus;
use App\Models\Agents\AgentSession;
use App\Models\Agents\AgentSessionEvaluation;
use App\Models\Agents\ReflectionRun;
use App\Models\Billing\CreditTransaction;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowVersion;
use App\Models\Workspaces\Workspace;
use Database\Factories\Runs\RunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

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
    use HasFactory, HasUuids;

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

    /**
     * Wall-clock time this run took, or null while it's still running (or
     * never started). Same formula `NodeRunDetailResource` already uses for
     * a single node run.
     */
    public function durationMs(): ?int
    {
        if ($this->started_at === null || $this->finished_at === null) {
            return null;
        }

        return $this->started_at->diffInMilliseconds($this->finished_at);
    }

    /**
     * Total credits this run has been billed, covering the three runnable
     * types that map cleanly onto a single run's charges:
     *
     * - A `Workflow` run: the sum of its `nodeRuns`' own
     *   `CreditTransaction`s (call `load('nodeRuns.creditTransaction')`
     *   first to avoid an N+1 per node run).
     * - An `AgentSession` turn: the one `AgentStep` transaction for the
     *   `AgentMessage` this run produced (`output.message_id`).
     * - An `AgentSessionEvaluation` grading or a `ReflectionRun` review: the
     *   one transaction for the runnable this run belongs to (`runnable_id`).
     *
     * Returns null for an `AgentEvalRun` run — that runnable bills one
     * `CreditTransaction` per graded case (`AgentEvalCaseResult`), not one
     * per run, so summing it here would mean joining through eval case
     * results rather than reusing this run's own relations; left for when
     * eval-run reporting needs it.
     *
     * `runnable_type` holds a morph-map alias for runs created through a
     * relation and a class name for ones created directly, so it's resolved
     * to a class before comparing.
     */
    public function totalCreditsUsed(): ?int
    {
        $runnableClass = Relation::getMorphedModel((string) $this->runnable_type) ?? $this->runnable_type;

        return match ($runnableClass) {
            Workflow::class => $this->relationLoaded('nodeRuns')
                ? $this->nodeRuns->sum(fn (NodeRun $nodeRun): int => $nodeRun->creditTransaction?->credits ?? 0)
                : $this->nodeRuns()->with('creditTransaction')->get()
                    ->sum(fn (NodeRun $nodeRun): int => $nodeRun->creditTransaction?->credits ?? 0),
            AgentSession::class => isset($this->output['message_id'])
                ? $this->creditsFor(CreditTransactionType::AgentStep, $this->output['message_id'])
                : null,
            AgentSessionEvaluation::class => $this->creditsFor(CreditTransactionType::SessionEvaluation, $this->runnable_id),
            ReflectionRun::class => $this->creditsFor(CreditTransactionType::Reflection, $this->runnable_id),
            default => null,
        };
    }

    private function creditsFor(CreditTransactionType $type, string $sourceId): ?int
    {
        return CreditTransaction::query()
            ->where('source_type', $type)
            ->where('source_id', $sourceId)
            ->value('credits');
    }
}
