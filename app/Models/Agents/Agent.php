<?php

namespace App\Models\Agents;

use App\Models\Artifacts\Artifact;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use Database\Factories\Agents\AgentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A conversational, tool-driven assistant — the "Agent" layer distinct from
 * the Workflow engine (docs/PLAN.md's "Architecture Overview"). Named `Agent`
 * (not `AgentModel`) per project convention; registered in
 * `AppServiceProvider::configureMorphMap()` as `TriggerTargetType::Agent`.
 */
#[Fillable(['workspace_id', 'name', 'slug', 'description', 'instructions', 'provider', 'model', 'temperature', 'settings', 'created_by'])]
class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'provider' => 'anthropic',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'temperature' => 'decimal:2',
            'settings' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AgentVersion::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AgentSession::class);
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class);
    }

    /**
     * Built-in/custom nodes attached as tools — see `AgentToolBinding`'s
     * docblock for the security-boundary design. Not a `BelongsToMany`
     * (unlike `workflows()` below) since built-in nodes have no row of
     * their own to belong to.
     */
    public function toolBindings(): HasMany
    {
        return $this->hasMany(AgentToolBinding::class);
    }

    /**
     * Workflows attached as tools (`Ai/Tools/WorkflowTool`) — a real
     * `BelongsToMany` since, unlike built-in nodes, a `Workflow` is a real
     * Eloquent row.
     */
    public function workflows(): BelongsToMany
    {
        return $this->belongsToMany(Workflow::class, 'agent_workflow')->withTimestamps();
    }

    /**
     * Reusable instruction snippets injected into the system prompt
     * alongside `instructions()` — see `Services\Agents\SkillInjector`.
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'agent_skill')->withTimestamps();
    }

    /**
     * Static "always know this" context, distinct from
     * `document_embeddings`/`SearchKnowledgeTool`'s "look this up when
     * relevant" — see docs/AGENTS_PLAN.md's "Knowledge / RAG" section.
     */
    public function knowledge(): HasMany
    {
        return $this->hasMany(AgentKnowledge::class);
    }

    /**
     * `document_embeddings.collection`s explicitly attached to this agent —
     * the opt-in scoping `ToolRegistry` prefers over its workspace-wide
     * fallback. See `AgentKnowledgeCollection`'s docblock.
     */
    public function knowledgeCollections(): HasMany
    {
        return $this->hasMany(AgentKnowledgeCollection::class);
    }

    /**
     * The `document_embeddings.collection` this agent's own exported
     * artifacts are indexed under — see `StoreArtifactAction`. Always
     * implicitly searchable by this agent, unlike `knowledgeCollections()`
     * which must be attached explicitly.
     */
    public function artifactKnowledgeCollection(): string
    {
        return "artifacts:{$this->id}";
    }

    /**
     * Durable key/value facts read/written across sessions.
     */
    public function memories(): HasMany
    {
        return $this->hasMany(AgentMemory::class);
    }

    /**
     * Saved test suites graded against this agent — see `EvalRunner`.
     */
    public function evalSuites(): HasMany
    {
        return $this->hasMany(AgentEvalSuite::class);
    }

    /**
     * Whether/how this agent periodically reviews its own past sessions —
     * see `Services\Agents\ReflectionAnalyzer`.
     */
    public function reflectionSettings(): HasOne
    {
        return $this->hasOne(ReflectionSettings::class);
    }

    public function reflectionRuns(): HasMany
    {
        return $this->hasMany(ReflectionRun::class);
    }

    /**
     * Whether/how this agent's live sessions are automatically graded after
     * each turn — see `Services\Agents\SessionEvaluator`.
     */
    public function evaluationSettings(): HasOne
    {
        return $this->hasOne(AgentEvaluationSettings::class);
    }

    public function sessionEvaluations(): HasMany
    {
        return $this->hasMany(AgentSessionEvaluation::class);
    }

    public function reflections(): HasMany
    {
        return $this->hasMany(Reflection::class);
    }
}
