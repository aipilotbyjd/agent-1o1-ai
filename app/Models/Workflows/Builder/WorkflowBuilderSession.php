<?php

namespace App\Models\Workflows\Builder;

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\ConfigSchemaValidator;
use App\Services\Workflows\NodeRegistry;
use Database\Factories\Workflows\Builder\WorkflowBuilderSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    /** @use HasFactory<WorkflowBuilderSessionFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'title' => 'Untitled workflow',
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

        $this->applyGraph($graph, $by);
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

        $this->applyGraph($graph, $by);
    }

    /**
     * Reject a node whose config does not match its type's schema.
     *
     * Validating here (not in the agent's tools) means the agent gets the specific
     * missing or mistyped field back as a tool result and can correct itself, instead
     * of the mistake surfacing much later as a failed publish — see
     * `Workflow::replaceGraph()`'s identical draft-time-only validation.
     *
     * @param  array<string, mixed>  $node
     */
    private function assertNodeIsValid(array $node): void
    {
        $registry = app(NodeRegistry::class);

        if (! $registry->has($node['type'])) {
            throw new InvalidArgumentException(
                "There is no node for type [{$node['type']}]. Use list_available_nodes to see valid types.",
            );
        }

        $schema = $registry->resolve($node['type'])->configSchema();
        $errors = app(ConfigSchemaValidator::class)->validate($schema, $node['config'] ?? []);

        if ($errors !== []) {
            throw new InvalidArgumentException("Node '{$node['key']}': ".implode(' ', $errors));
        }
    }

    public function removeNode(string $key, ?User $by = null): void
    {
        $graph = $this->currentGraph();

        $graph['nodes'] = array_values(array_filter($graph['nodes'], fn (array $node): bool => $node['key'] !== $key));
        $graph['edges'] = array_values(array_filter(
            $graph['edges'],
            fn (array $edge): bool => $edge['from'] !== $key && $edge['to'] !== $key,
        ));

        $this->applyGraph($graph, $by);
    }

    public function connect(string $from, string $to, ?string $condition = null, ?User $by = null): void
    {
        $graph = $this->currentGraph();
        $keys = collect($graph['nodes'])->pluck('key');

        if (! $keys->contains($from) || ! $keys->contains($to)) {
            throw new InvalidArgumentException('Both nodes must exist in the draft before they can be connected.');
        }

        $graph['edges'][] = ['from' => $from, 'to' => $to, 'condition' => $condition];

        $this->applyGraph($graph, $by);
    }

    public function disconnect(string $from, string $to, ?User $by = null): void
    {
        $graph = $this->currentGraph();

        $graph['edges'] = array_values(array_filter(
            $graph['edges'],
            fn (array $edge): bool => ! ($edge['from'] === $from && $edge['to'] === $to),
        ));

        $this->applyGraph($graph, $by);
    }

    /**
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public function currentGraph(): array
    {
        return $this->draft_graph ?? ['nodes' => [], 'edges' => []];
    }

    /**
     * Persist a mutated graph as both the live draft and a versioned snapshot, so every
     * agent-driven edit is individually diffable/undoable via draftVersions().
     *
     * @param  array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}  $graph
     */
    private function applyGraph(array $graph, ?User $by): void
    {
        $this->draftVersions()->create([
            'triggered_by' => $by?->id,
            'graph_snapshot' => $graph,
        ]);

        $this->update([
            'draft_graph' => $graph,
            'draft_lock_version' => $this->draft_lock_version + 1,
        ]);
    }
}
