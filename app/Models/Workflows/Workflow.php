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
use Illuminate\Support\Facades\DB;

#[Fillable(['workspace_id', 'folder_id', 'name', 'slug', 'description', 'created_by'])]
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
            if (! $registry->has($node['type'])) {
                continue;
            }

            $schema = $registry->resolve($node['type'])->configSchema();

            foreach ($configValidator->validate($schema, $node['config'] ?? []) as $error) {
                $errors[] = "Node '{$node['key']}': {$error}";
            }
        }

        if ($errors !== []) {
            throw new WorkflowValidationException($errors);
        }

        DB::transaction(function () use ($nodes, $edges): void {
            $this->edges()->delete();
            $this->nodes()->delete();

            $nodeIdsByKey = [];

            foreach ($nodes as $node) {
                $nodeIdsByKey[$node['key']] = $this->nodes()->create([
                    'key' => $node['key'],
                    'type' => $node['type'],
                    'config' => $node['config'] ?? [],
                    'position' => $node['position'] ?? null,
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
     * Snapshot the current draft graph as an immutable, publish-pinned
     * `WorkflowVersion` after running the full `GraphValidator` sequence —
     * see docs/WORKFLOWS_PLAN.md's `workflow_versions` section for why runs
     * pin to a version rather than the live draft.
     *
     * @throws WorkflowValidationException
     */
    public function publishVersion(?string $notes = null, ?User $publisher = null): WorkflowVersion
    {
        $nodes = $this->nodes()->get()
            ->map(fn (WorkflowNode $node): array => [
                'key' => $node->key,
                'type' => $node->type,
                'config' => $node->config ?? [],
            ])
            ->all();

        $nodeKeysById = $this->nodes()->get()->pluck('key', 'id');

        $edges = $this->edges()->get()
            ->map(fn (WorkflowEdge $edge): array => [
                'from' => $nodeKeysById[$edge->from_node_id],
                'to' => $nodeKeysById[$edge->to_node_id],
                'condition' => $edge->condition,
            ])
            ->all();

        $errors = app(GraphValidator::class)->validate($nodes, $edges);

        if ($errors !== []) {
            throw new WorkflowValidationException($errors);
        }

        return DB::transaction(function () use ($nodes, $edges, $notes, $publisher): WorkflowVersion {
            $nextVersion = ((int) $this->versions()->max('version')) + 1;

            $version = $this->versions()->create([
                'version' => $nextVersion,
                'graph' => ['nodes' => $nodes, 'edges' => $edges],
                'notes' => $notes,
                'published_by' => $publisher?->id,
            ]);

            $this->forceFill([
                'current_version_id' => $version->id,
                'has_unpublished_changes' => false,
                'status' => 'published',
            ])->save();

            return $version;
        });
    }
}
