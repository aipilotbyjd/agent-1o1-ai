<?php

namespace App\Models\Agents;

use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * `version` is engine-managed — not in `#[Fillable]`, incremented via
 * `forceFill()`/`increment()` whenever `instructions` changes (see
 * `SkillController::update()`), same "don't let the model behind an
 * in-flight session change under it" reasoning as `Agent`.
 */
#[Fillable(['workspace_id', 'created_by', 'name', 'slug', 'description', 'category', 'icon', 'color', 'tags', 'instructions', 'is_shared'])]
class Skill extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_shared' => false,
        'version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_shared' => 'boolean',
            'version' => 'integer',
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

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_skill')->withTimestamps();
    }
}
