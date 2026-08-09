<?php

namespace App\Models\Nodes;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The product-facing node taxonomy — what the node picker groups by. Every
 * `NodeContract::category()` must return a string matching a row's `slug`
 * here. See docs/STRUCTURE.md's "How node folders tie to NodeCategory".
 */
#[Fillable(['name', 'slug', 'description', 'icon', 'color', 'sort_order', 'kind'])]
class NodeCategory extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
        'kind' => 'core',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function customNodes(): HasMany
    {
        return $this->hasMany(CustomNode::class, 'category_id');
    }
}
