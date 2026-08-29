<?php

namespace App\Models\Ai;

use App\Models\Agents\Agent;
use Database\Factories\Ai\ModelCatalogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The public, brand-facing model identity shown to end users (agent/workflow
 * model pickers) — deliberately decoupled from how a prompt actually gets
 * executed. What runs a given entry (a direct provider, or one/more
 * aggregators like Fireworks/Together/OpenRouter, in priority order) lives
 * in `ModelRoute` and is never exposed through user-facing API responses.
 * See `Services\Ai\ModelCatalogResolver`.
 */
#[Fillable(['slug', 'display_name', 'brand', 'capabilities', 'is_active', 'is_internal', 'sort_order'])]
class ModelCatalog extends Model
{
    /** @use HasFactory<ModelCatalogFactory> */
    use HasFactory;

    protected $table = 'model_catalog';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'is_active' => 'boolean',
            'is_internal' => 'boolean',
        ];
    }

    /**
     * Candidate execution backends for this entry, in the order
     * `ModelCatalogResolver` should try them.
     */
    public function routes(): HasMany
    {
        return $this->hasMany(ModelRoute::class)->orderBy('priority');
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }
}
