<?php

namespace App\Models\Nodes;

use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Nodes\CustomNodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A workspace-defined node — resolved by `NodeRegistry` from a `custom:{id}`
 * type string instead of a PHP `NodeContract` class, and executed by
 * `App\Nodes\Custom\CustomHttpNode` interpreting `implementation`.
 *
 * A row without an `implementation` is a definition with no behavior: it is
 * still listed in the node picker and can still be placed on a canvas, but
 * `NodeRegistry::has()` answers false for it and a run that reaches it fails
 * that one node. `isExecutable()` is the single place that distinction lives.
 */
#[Fillable([
    'workspace_id', 'category_id', 'type', 'name', 'description', 'icon', 'color',
    'config_schema', 'implementation', 'input_schema', 'output_schema', 'credential_type', 'is_active', 'created_by',
])]
class CustomNode extends Model
{
    /** @use HasFactory<CustomNodeFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config_schema' => 'array',
            'implementation' => 'array',
            'input_schema' => 'array',
            'output_schema' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The `custom:{id}` string a graph stores in `workflow_nodes.type` to
     * point at this row.
     */
    public function nodeType(): string
    {
        return 'custom:'.$this->id;
    }

    /**
     * Whether the engine can actually run this node. An inactive row, or one
     * whose author never supplied an `implementation`, is a catalogue entry
     * only.
     */
    public function isExecutable(): bool
    {
        return $this->is_active && ($this->implementation['kind'] ?? null) !== null;
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(NodeCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
