<?php

namespace App\Models\Workflows;

use App\Exceptions\WorkflowValidationException;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\ConfigSchemaValidator;
use App\Services\Workflows\GraphValidator;
use App\Services\Workflows\NodeRegistry;
use Database\Factories\Workflows\WorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

#[Fillable(['workspace_id', 'folder_id', 'name', 'slug', 'description', 'input_schema', 'created_by'])]
class Workflow extends Model
{
    /** @use HasFactory<WorkflowFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'has_unpublished_changes' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_unpublished_changes' => 'boolean',
            'input_schema' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_workflow');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'current_version_id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(WorkflowNode::class);
    }

    public function edges(): HasMany
    {
        return $this->hasMany(WorkflowEdge::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(Run::class);
    }

    public function isPublished(): bool
    {
        return $this->current_version_id !== null;
    }

    /**
     * Replace the draft graph wholesale. Only per-node config-schema issues
     * are checked here (not the full `GraphValidator` sequence — a draft is
     * allowed to be mid-edit, e.g. temporarily missing an entry node) — see
     * docs/WORKFLOWS_PLAN.md's "Validation" section.
     *
     * @param  array{nodes?: array<int, array{key: string, type: string, config?: array<string, mixed>, position?: array<string, mixed>|null}>, edges?: array<int, array{from: string, to: string, condition?: string|null}>}  $graph
     *
     * @throws WorkflowValidationException
     */
    public function replaceGraph(array $graph): void
    {
        $nodes = $graph['nodes'] ?? [];
        $edges = $graph['edges'] ?? [];

        $registry = app(NodeRegistry::class);
        $configValidator = app(ConfigSchemaValidator::class);
        $errors = [];

        foreach ($nodes as $node) {
            if (! $registry->has($node['type'], $this->workspace_id)) {
                continue;
            }

            $schema = $registry->resolve($node['type'], $this->workspace_id)->configSchema();

            foreach ($configValidator->validate($schema, $node['config'] ?? []) as $error) {
                $errors[] = "Node '{$node['key']}': {$error}";
            }
        }

        if ($errors !== []) {
            throw new WorkflowValidationException($errors);
        }

        DB::transaction(function () use ($nodes, $edges): void {
            // Pin state lives on the node row, but every save deletes and
            // recreates the rows wholesale (fresh IDs) — snapshot pinned
            // data keyed by `key` first so it survives an ordinary canvas
            // save. A node whose `type` changed loses its pin: the output
            // shape it was pinned against may no longer apply.
            $previousNodesByKey = $this->nodes()->get()->keyBy('key');

            $this->edges()->delete();
            $this->nodes()->delete();

            $nodeIdsByKey = [];

            foreach ($nodes as $node) {
                $previous = $previousNodesByKey->get($node['key']);
                $carryPin = $previous !== null && $previous->type === $node['type'];

                $nodeIdsByKey[$node['key']] = $this->nodes()->create([
                    'key' => $node['key'],
                    'type' => $node['type'],
                    'config' => $node['config'] ?? [],
                    'position' => $node['position'] ?? null,
                    'pinned_data' => $carryPin ? $previous->pinned_data : null,
                    'pinned_at' => $carryPin ? $previous->pinned_at : null,
                    'pinned_by' => $carryPin ? $previous->pinned_by : null,
                ])->id;
            }

            foreach ($edges as $edge) {
                $this->edges()->create([
                    'from_node_id' => $nodeIdsByKey[$edge['from']],
                    'to_node_id' => $nodeIdsByKey[$edge['to']],
                    'condition' => $edge['condition'] ?? null,
                ]);
            }

            $this->forceFill(['has_unpublished_changes' => true])->save();
        });
    }

    /**
     * The live draft as the same `{nodes, edges}` shape a `WorkflowVersion`
     * snapshot uses — what `GraphValidator`, `DryRunner` and
     * `publishVersion()` all consume, so the thing being validated is always
     * the thing that would be published.
     *
     * @return array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>, pinned_data: array<string, mixed>|null}>, edges: array<int, array{from: string, to: string, condition: string|null}>}
     */
    public function draftGraph(): array
    {
        $nodes = $this->nodes()->get();

        $nodeKeysById = $nodes->pluck('key', 'id');

        return [
            'nodes' => $nodes
                ->map(fn (WorkflowNode $node): array => [
                    'key' => $node->key,
                    'type' => $node->type,
                    'config' => $node->config ?? [],
                    'pinned_data' => $node->pinned_data,
                ])
                ->all(),
            'edges' => $this->edges()->get()
                ->map(fn (WorkflowEdge $edge): array => [
                    'from' => $nodeKeysById[$edge->from_node_id],
                    'to' => $nodeKeysById[$edge->to_node_id],
                    'condition' => $edge->condition,
                ])
                ->all(),
        ];
    }

    /**
     * Snapshot the current draft graph as an immutable, publish-pinned
     * `WorkflowVersion` after running the full `GraphValidator` sequence —
     * see docs/WORKFLOWS_PLAN.md's `workflow_versions` section for why runs
     * pin to a version rather than the live draft.
     *
     * @throws WorkflowValidationException
     */
    public function publishVersion(?string $notes = null, ?User $publisher = null): WorkflowVersion
    {
        ['nodes' => $nodes, 'edges' => $edges] = $this->draftGraph();

        $errors = app(GraphValidator::class)->validate($nodes, $edges, $this->workspace_id);

        if ($errors !== []) {
            throw new WorkflowValidationException($errors);
        }

        return DB::transaction(function () use ($nodes, $edges, $notes, $publisher): WorkflowVersion {
            $graph = ['nodes' => $nodes, 'edges' => $edges];

            // `lockForUpdate()` on the watermark read, plus a retry on the
            // `unique(workflow_id, version)` constraint — the same pairing
            // `AgentVersioner::snapshot()` uses. Two people hitting Publish
            // at once would otherwise both read the same `max('version')`
            // and the loser's request would die on the constraint. The lock
            // alone isn't enough: on a workflow's *first* publish there are
            // no rows to lock, so both readers see null and both try 1.
            //
            // The first attempt runs in a nested transaction — i.e. a
            // savepoint — because Postgres aborts the whole transaction on a
            // constraint violation, which would leave the retry below unable
            // to issue any query at all. Rolling back to the savepoint keeps
            // the outer transaction usable.
            try {
                $version = DB::transaction(fn (): WorkflowVersion => $this->createVersion(
                    ((int) $this->versions()->lockForUpdate()->max('version')) + 1,
                    $graph,
                    $notes,
                    $publisher,
                ));
            } catch (UniqueConstraintViolationException) {
                $version = $this->createVersion(
                    ((int) $this->versions()->max('version')) + 1,
                    $graph,
                    $notes,
                    $publisher,
                );
            }

            $this->forceFill([
                'current_version_id' => $version->id,
                'has_unpublished_changes' => false,
                'status' => 'published',
            ])->save();

            return $version;
        });
    }

    /**
     * @param  array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}  $graph
     */
    private function createVersion(int $version, array $graph, ?string $notes, ?User $publisher): WorkflowVersion
    {
        return $this->versions()->create([
            'version' => $version,
            'graph' => $graph,
            'notes' => $notes,
            'published_by' => $publisher?->id,
        ]);
    }
}
