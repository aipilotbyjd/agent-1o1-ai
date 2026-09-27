<?php

namespace App\Models\Workflows\Builder;

use App\Enums\Workflows\BuilderMessageStatus;
use App\Enums\Workflows\BuilderSessionStatus;
use App\Enums\Workflows\FlowControlNodeType;
use App\Exceptions\WorkflowBuilderConflictException;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\ConfigSchemaValidator;
use App\Services\Workflows\GraphValidator;
use App\Services\Workflows\NodeRegistry;
use Database\Factories\Workflows\Builder\WorkflowBuilderSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A chat session that edits a `draft_graph` on the user's behalf via
 * `WorkflowBuilderAgent`'s tools — every mutation method here is what those
 * tools actually call. `draft_graph` uses this project's own graph shape
 * (`{nodes: [...], edges: [...]}`, each node `{key, type, config, position}`)
 * — exactly what `Workflow::replaceGraph()` expects, so promoting a draft to
 * a real workflow needs no adapter.
 */
#[Fillable([
    'workspace_id', 'user_id', 'workflow_id', 'conversation_id', 'title',
    'draft_graph', 'draft_lock_version', 'status', 'last_activity_at',
])]
class WorkflowBuilderSession extends Model
{
    public const string DEFAULT_TITLE = 'Untitled workflow';

    /** @use HasFactory<WorkflowBuilderSessionFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'title' => self::DEFAULT_TITLE,
        'draft_lock_version' => 0,
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'draft_graph' => 'array',
            'draft_lock_version' => 'integer',
            'status' => BuilderSessionStatus::class,
            'last_activity_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function draftVersions(): HasMany
    {
        return $this->hasMany(WorkflowBuilderDraftVersion::class, 'session_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WorkflowBuilderMessage::class, 'session_id');
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array{x: float, y: float}|null  $position
     */
    public function addNode(string $key, string $type, array $config = [], ?array $position = null, ?User $by = null): void
    {
        $graph = $this->currentGraph();

        if (collect($graph['nodes'])->contains('key', $key)) {
            throw new InvalidArgumentException("Node [{$key}] already exists.");
        }

        $node = ['key' => $key, 'type' => $type, 'config' => $config, 'position' => $position];

        $this->assertNodeIsValid($node);

        $graph['nodes'][] = $node;

        $this->applyGraph($graph, $by, "Added node {$key}");
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function updateNode(string $key, array $config, ?User $by = null): void
    {
        $graph = $this->currentGraph();
        $found = false;

        foreach ($graph['nodes'] as &$node) {
            if ($node['key'] === $key) {
                $node['config'] = [...($node['config'] ?? []), ...$config];
                $this->assertNodeIsValid($node);
                $found = true;
                break;
            }
        }
        unset($node);

        if (! $found) {
            throw new InvalidArgumentException("Node [{$key}] was not found.");
        }

        $this->applyGraph($graph, $by, "Updated node {$key}");
    }

    /**
     * Reject a node whose config does not match its type's schema.
     *
     * Validating here (not in the agent's tools) means the agent gets the specific
     * missing or mistyped field back as a tool result and can correct itself, instead
     * of the mistake surfacing much later as a failed publish — see
     * `Workflow::replaceGraph()`'s identical draft-time-only validation.
     *
     * Flow-control types (loop, subflow, wait, human_approval, join_paths)
     * are placeable too; the two that run another workflow must point at a
     * published one in this workspace, which the engine would otherwise
     * only discover mid-run.
     *
     * @param  array<string, mixed>  $node
     */
    private function assertNodeIsValid(array $node): void
    {
        $registry = app(NodeRegistry::class);
        $schema = $registry->configSchemaFor($node['type']);

        if ($schema === null) {
            throw new InvalidArgumentException(
                "There is no node for type [{$node['type']}]. Use list_available_nodes to see valid types.",
            );
        }

        $config = $node['config'] ?? [];
        $errors = app(ConfigSchemaValidator::class)->validate($schema, $config);

        if ($errors !== []) {
            throw new InvalidArgumentException("Node '{$node['key']}': ".implode(' ', $errors));
        }

        $flowControl = FlowControlNodeType::tryFrom($node['type']);

        if ($flowControl !== null && array_key_exists('_loop', $config)) {
            throw new InvalidArgumentException("Node '{$node['key']}': loop mode (_loop) isn't supported on flow-control nodes.");
        }

        if ($flowControl?->runsChildWorkflow()) {
            $this->assertRunnableChildWorkflow($node['key'], (string) $config['workflow_id']);
        }
    }

    private function assertRunnableChildWorkflow(string $key, string $workflowId): void
    {
        if ($this->workflow_id !== null && $workflowId === $this->workflow_id) {
            throw new InvalidArgumentException("Node '{$key}': a workflow can't run itself as a child workflow.");
        }

        $child = Str::isUuid($workflowId)
            ? Workflow::query()->whereKey($workflowId)->where('workspace_id', $this->workspace_id)->first()
            : null;

        if ($child === null) {
            throw new InvalidArgumentException("Node '{$key}': there is no workflow [{$workflowId}] in this workspace. Use list_workflows to find one.");
        }

        if (! $child->isPublished()) {
            throw new InvalidArgumentException("Node '{$key}': workflow [{$child->name}] isn't published yet, so it can't run as a child workflow.");
        }
    }

    public function removeNode(string $key, ?User $by = null): void
    {
        $graph = $this->currentGraph();

        if (! collect($graph['nodes'])->contains('key', $key)) {
            throw new InvalidArgumentException("Node [{$key}] was not found.");
        }

        $graph['nodes'] = array_values(array_filter($graph['nodes'], fn (array $node): bool => $node['key'] !== $key));
        $graph['edges'] = array_values(array_filter(
            $graph['edges'],
            fn (array $edge): bool => $edge['from'] !== $key && $edge['to'] !== $key,
        ));

        $this->applyGraph($graph, $by, "Removed node {$key}");
    }

    public function connect(string $from, string $to, ?string $condition = null, ?User $by = null): void
    {
        $graph = $this->currentGraph();
        $keys = collect($graph['nodes'])->pluck('key');

        if (! $keys->contains($from) || ! $keys->contains($to)) {
            throw new InvalidArgumentException('Both nodes must exist in the draft before they can be connected.');
        }

        $exists = collect($graph['edges'])->contains(
            fn (array $edge): bool => $edge['from'] === $from && $edge['to'] === $to && ($edge['condition'] ?? null) === $condition,
        );

        if ($exists) {
            throw new InvalidArgumentException("[{$from}] is already connected to [{$to}] with that condition.");
        }

        $graph['edges'][] = ['from' => $from, 'to' => $to, 'condition' => $condition];

        $this->applyGraph($graph, $by, "Connected {$from} → {$to}");
    }

    /**
     * Remove the edges from `$from` to `$to` — every one of them, or with
     * `$onlyCondition` just the one carrying `$condition` (`null` meaning the
     * unconditional edge), e.g. to turn an always-edge into an error path.
     */
    public function disconnect(string $from, string $to, ?User $by = null, ?string $condition = null, bool $onlyCondition = false): void
    {
        $graph = $this->currentGraph();
        $edgeCount = count($graph['edges']);

        $graph['edges'] = array_values(array_filter(
            $graph['edges'],
            fn (array $edge): bool => ! ($edge['from'] === $from
                && $edge['to'] === $to
                && (! $onlyCondition || ($edge['condition'] ?? null) === $condition)),
        ));

        if (count($graph['edges']) === $edgeCount) {
            throw new InvalidArgumentException($onlyCondition
                ? "There is no edge from [{$from}] to [{$to}] with that condition."
                : "There is no edge from [{$from}] to [{$to}].");
        }

        $this->applyGraph($graph, $by, "Disconnected {$from} → {$to}");
    }

    /**
     * Replace the whole draft with the canvas's copy of it — how edits made
     * by hand (drag, delete, add from the library) reach the draft the agent
     * reads. Refused when `$expectedLockVersion` is behind, so a canvas that
     * hasn't seen the agent's latest edits can't silently undo them.
     *
     * @param  array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}  $graph
     *
     * @throws InvalidArgumentException
     * @throws WorkflowBuilderConflictException
     */
    public function replaceDraft(array $graph, int $expectedLockVersion, ?User $by = null): void
    {
        if ($expectedLockVersion !== $this->draft_lock_version) {
            throw WorkflowBuilderConflictException::staleDraft();
        }

        $normalized = [
            'nodes' => array_map(fn (array $node): array => [
                'key' => $node['key'],
                'type' => $node['type'],
                'config' => $node['config'] ?? [],
                'position' => $node['position'] ?? null,
            ], $graph['nodes']),
            'edges' => GraphValidator::uniqueEdges(array_map(fn (array $edge): array => [
                'from' => $edge['from'],
                'to' => $edge['to'],
                'condition' => $edge['condition'] ?? null,
            ], $graph['edges'])),
        ];

        $structuralErrors = app(GraphValidator::class)->structuralErrors($normalized['nodes'], $normalized['edges']);

        if ($structuralErrors !== []) {
            throw new InvalidArgumentException(implode(' ', $structuralErrors));
        }

        foreach ($normalized['nodes'] as $node) {
            $this->assertNodeIsValid($node);
        }

        $this->applyGraph($normalized, $by, 'Synced from canvas');
    }

    /**
     * Roll the draft back to an earlier snapshot. The restore is itself a new
     * snapshot, so it can be undone the same way.
     */
    public function restoreVersion(WorkflowBuilderDraftVersion $version, ?User $by = null): void
    {
        if ($version->session_id !== $this->id) {
            throw new InvalidArgumentException('That version belongs to a different session.');
        }

        $this->applyGraph(
            $version->graph_snapshot,
            $by,
            'Restored '.($version->label ?? 'version from '.$version->created_at?->format('M j, H:i')),
        );
    }

    /**
     * @throws WorkflowBuilderConflictException
     */
    public function assertEditable(): void
    {
        if (! $this->status->isEditable()) {
            throw WorkflowBuilderConflictException::archived();
        }
    }

    public function hasReplyInFlight(): bool
    {
        return $this->messages()
            ->where('role', 'assistant')
            ->whereIn('processing_status', [BuilderMessageStatus::Pending, BuilderMessageStatus::Processing])
            ->exists();
    }

    /**
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public function currentGraph(): array
    {
        return $this->draft_graph ?? ['nodes' => [], 'edges' => []];
    }

    /**
     * Persist a mutated graph as both the live draft and a labelled snapshot, so every
     * edit is individually diffable/undoable via draftVersions().
     *
     * The write only lands if `draft_lock_version` still matches the one this
     * instance read: the agent (inside a queued turn) and the canvas (over
     * HTTP) edit the same draft, and a stale writer would otherwise silently
     * erase the other's change.
     *
     * @param  array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}  $graph
     *
     * @throws WorkflowBuilderConflictException
     */
    private function applyGraph(array $graph, ?User $by, string $label): void
    {
        $nextVersion = $this->draft_lock_version + 1;

        DB::transaction(function () use ($graph, $by, $label, $nextVersion): void {
            $updated = static::query()
                ->whereKey($this->getKey())
                ->where('draft_lock_version', $this->draft_lock_version)
                ->update([
                    'draft_graph' => json_encode($graph, JSON_THROW_ON_ERROR),
                    'draft_lock_version' => $nextVersion,
                    'last_activity_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                throw WorkflowBuilderConflictException::staleDraft();
            }

            $this->draftVersions()->create([
                'triggered_by' => $by?->id,
                'graph_snapshot' => $graph,
                'label' => $label,
            ]);
        });

        $this->forceFill([
            'draft_graph' => $graph,
            'draft_lock_version' => $nextVersion,
            'last_activity_at' => now(),
        ])->syncOriginal();
    }
}
